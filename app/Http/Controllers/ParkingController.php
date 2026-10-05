<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParkingCheckoutRequest;
use App\Http\Requests\ParkingSessionRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Models\ParkingSession;
use App\Services\ParkingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ParkingController extends Controller
{
    public function __construct(private readonly ParkingService $parking) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $sessions = ParkingSession::query()
            ->with(['associate'])
            ->when($status === 'parked', fn ($q) => $q->whereNull('exited_at'))
            ->when($status === 'exited', fn ($q) => $q->whereNotNull('exited_at'))
            ->orderByDesc('entered_at')
            ->paginate(20)
            ->withQueryString();

        return view('parking.index', [
            'sessions' => $sessions,
            'filters' => ['status' => $status],
            'monthly' => $this->parking->monthlySummary(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = ['associates' => Associate::where('is_active', true)->orderBy('name')->get()];

        return $request->ajax() ? view('parking._form', $data) : view('parking.create', $data);
    }

    public function store(ParkingSessionRequest $request): RedirectResponse
    {
        $session = $this->parking->register($request->validated(), $request->user()->id);

        AuditLog::record('parking.register', 'parking_session', (string) $session->id, 'success');

        return redirect()->route('parking.index')->with('success', 'Entrada registrada.');
    }

    public function edit(ParkingSession $parkingSession, Request $request): View
    {
        $data = [
            'session' => $parkingSession,
            'associates' => Associate::where('is_active', true)->orderBy('name')->get(),
        ];

        return $request->ajax() ? view('parking._form', $data) : view('parking.edit', $data);
    }

    public function update(ParkingSessionRequest $request, ParkingSession $parkingSession): RedirectResponse
    {
        try {
            $this->parking->update($parkingSession, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['plate' => $e->getMessage()])->withInput();
        }

        AuditLog::record('parking.update', 'parking_session', (string) $parkingSession->id, 'success');

        return redirect()->route('parking.index')->with('success', 'Registro actualizado.');
    }

    public function checkoutForm(ParkingSession $parkingSession, Request $request): View
    {
        $data = ['session' => $parkingSession];

        return $request->ajax() ? view('parking._checkout-form', $data) : view('parking.checkout', $data);
    }

    public function checkout(ParkingCheckoutRequest $request, ParkingSession $parkingSession): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->parking->checkout($parkingSession, $data['exited_at'], isset($data['amount']) ? (float) $data['amount'] : null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['exited_at' => $e->getMessage()]);
        }

        AuditLog::record('parking.checkout', 'parking_session', (string) $parkingSession->id, 'success');

        return redirect()->route('parking.index')->with('success', 'Salida registrada.');
    }
}
