<?php

namespace App\Http\Controllers;

use App\Http\Requests\RentalCancelRequest;
use App\Http\Requests\RentalRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Models\Rental;
use App\Models\Space;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

class RentalController extends Controller
{
    public function __construct(private readonly RentalService $rentals) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $spaceId = $request->integer('space_id') ?: null;

        $rentals = Rental::query()
            ->with(['space', 'associate'])
            ->when($status && in_array($status, Rental::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($spaceId, fn ($q) => $q->where('space_id', $spaceId))
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('rentals.index', [
            'rentals' => $rentals,
            'spaces' => Space::where('is_active', true)->orderBy('name')->get(),
            'filters' => ['status' => $status, 'space_id' => $spaceId],
        ]);
    }

    /**
     * Same month-browser shape as associates.birthdays (?month=YYYY-MM),
     * but grouping Rental bookings instead of associate milestones.
     */
    public function calendar(Request $request): View
    {
        $today = CarbonImmutable::today();

        $month = $today;
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month', ''))) {
            $month = CarbonImmutable::createFromFormat('Y-m', $request->query('month'))->startOfMonth();
        }

        $todayRentals = Rental::query()
            ->with(['space', 'associate'])
            ->where('status', '!=', Rental::STATUS_CANCELADA)
            ->whereDate('starts_at', $today->toDateString())
            ->orderBy('starts_at')
            ->get();

        return view('rentals.calendar', [
            'today' => $today,
            'todayRentals' => $todayRentals,
            'month' => $month,
            'monthRentals' => $this->rentals->forMonth($month->year, $month->month),
            'prevMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
        ]);
    }

    public function create(Request $request): View
    {
        $data = [
            'spaces' => Space::where('is_active', true)->orderBy('name')->get(),
            'associates' => Associate::where('is_active', true)->orderBy('name')->get(),
        ];

        // Same ajax/full-page branch every other modal-based form in this
        // app uses (js-modal-link opens the bare form as an overlay; a
        // direct URL visit or no-JS fallback gets the full page).
        return $request->ajax() ? view('rentals._form', $data) : view('rentals.create', $data);
    }

    public function store(RentalRequest $request): RedirectResponse
    {
        $rental = $this->rentals->create($request->validated(), $request->user()->id);

        AuditLog::record('rental.create', 'rental', (string) $rental->id, 'success');

        return redirect()->route('rentals.show', $rental)->with('success', 'Cotización de alquiler creada.');
    }

    public function show(Rental $rental): View
    {
        $rental->load(['space', 'associate', 'creator', 'cancelledBy']);

        return view('rentals.show', ['rental' => $rental]);
    }

    public function edit(Rental $rental): View
    {
        return view('rentals.edit', [
            'rental' => $rental,
            'spaces' => Space::where('is_active', true)->orderBy('name')->get(),
            'associates' => Associate::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(RentalRequest $request, Rental $rental): RedirectResponse
    {
        try {
            $this->rentals->update($rental, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        AuditLog::record('rental.update', 'rental', (string) $rental->id, 'success');

        return redirect()->route('rentals.show', $rental)->with('success', 'Alquiler actualizado correctamente.');
    }

    public function confirm(Rental $rental): RedirectResponse
    {
        try {
            $this->rentals->confirm($rental);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record('rental.confirm', 'rental', (string) $rental->id, 'success');

        return redirect()->route('rentals.show', $rental)->with('success', 'Reserva confirmada.');
    }

    public function bill(Rental $rental): RedirectResponse
    {
        try {
            $this->rentals->bill($rental);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record('rental.bill', 'rental', (string) $rental->id, 'success');

        return redirect()->route('rentals.show', $rental)->with('success', 'Alquiler marcado como facturado.');
    }

    public function cancel(RentalCancelRequest $request, Rental $rental): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->rentals->cancel($rental, $data['reason'], $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        AuditLog::record('rental.cancel', 'rental', (string) $rental->id, 'success', ['reason' => $data['reason']]);

        return redirect()->route('rentals.show', $rental)->with('success', 'Alquiler cancelado.');
    }

    /**
     * "Ver el detalle de factura del alquiler" as a downloadable PDF —
     * generated on demand (same isRemoteEnabled=false Dompdf pattern as
     * ExportService/AssociateInscriptionService), not stored, since
     * unlike AssociateDocument there's no document list this needs to
     * join. The heading changes with the rental's own status instead of
     * being a separate "cotización" template.
     */
    public function pdf(Rental $rental): Response
    {
        $rental->load(['space', 'associate']);

        $options = new DompdfOptions;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('rentals.pdf.detail', ['rental' => $rental])->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $label = $rental->status === Rental::STATUS_COTIZADA ? 'cotizacion' : 'alquiler';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$label.'-'.$rental->id.'.pdf"',
        ]);
    }
}
