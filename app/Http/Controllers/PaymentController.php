<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentQuickRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\PaymentVoidRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PaymentController extends Controller
{
    /**
     * "Estado" options of the Cuotas tab. The keys are what the URL
     * carries; PAGADA/PARCIAL map to the stored invoice status, "no
     * pagadas" is everything with a balance and "vencidas" the subset of
     * those already past their due date (computed on read, see
     * Invoice::effectiveStatus()).
     */
    public const STATUS_FILTERS = [
        'pagadas' => 'Pagadas',
        'no_pagadas' => 'No pagadas',
        'parciales' => 'Pago parcial',
        'vencidas' => 'Vencidas',
    ];

    public const MOVEMENT_FILTERS = [
        'validos' => 'Válidos',
        'anulados' => 'Anulados',
    ];

    private const MONTHS = [
        1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    /**
     * The Pagos module is organized around the monthly fee (one invoice
     * per associate per period): the default tab lists those cuotas with
     * their paid/unpaid state, the second one is the ledger of payment
     * movements (what the module used to show), kept for auditing voids.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'movimientos' ? 'movimientos' : 'cuotas';
        $filters = $this->filtersFrom($request);

        $term = $filters['q'];
        $associateScope = function (Builder $query) use ($filters) {
            $query
                ->when($filters['associate_id'], fn ($q) => $q->where('id', $filters['associate_id']))
                ->when($filters['sectorista'], fn ($q) => $q->where('sectorista', $filters['sectorista']))
                ->when($filters['category'], fn ($q) => $q->where('category', $filters['category']));
        };
        $associateSearch = function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('ruc', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%");
        };

        $data = [
            'tab' => $tab,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'months' => self::MONTHS,
        ];

        if ($tab === 'cuotas') {
            // Built as closures so the page's totals can be computed on a
            // second, un-paginated copy of the same filters. The KPI cards
            // double as status quick-filters, so `$scoped` deliberately
            // leaves `status` out: picking "Vencidas" must not turn the
            // "Pagado" card into "S/ 0" — the four cards keep describing
            // the whole set the other filters select.
            $scoped = fn () => Invoice::query()
                ->whereHas('associate', $associateScope)
                // A comprobante number typed in the search box finds its
                // cuota too, not only associate names.
                ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term, $associateSearch) {
                    $w->where('receipt_number', 'like', "%{$term}%")
                        ->orWhereHas('associate', $associateSearch);
                }))
                ->when($filters['year'], fn ($q) => $q->where('period', 'like', $filters['year'].'-%'))
                ->when($filters['month'], fn ($q) => $q->where('period', 'like', '%-'.str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT)))
                // Cuotas that received at least one valid payment through
                // the chosen channel ("sin_metodo" = a payment with none).
                ->when($filters['method'], fn ($q) => $q->whereHas('activePayments', fn ($p) => $filters['method'] === 'sin_metodo'
                    ? $p->whereNull('method')
                    : $p->where('method', $filters['method'])));

            $cuotas = fn () => $scoped()
                ->when($filters['status'], fn ($q) => match ($filters['status']) {
                    'pagadas' => $q->paid(),
                    'no_pagadas' => $q->unpaid(),
                    'parciales' => $q->where('status', Invoice::STATUS_PARCIAL)->whereNull('voided_at'),
                    'vencidas' => $q->overdue(),
                });

            $data['invoices'] = $cuotas()
                ->with(['associate', 'activePayments'])
                ->orderByDesc('period')
                ->orderBy(Associate::select('name')->whereColumn('associates.id', 'invoices.associate_id'))
                ->paginate(20)
                ->withQueryString();

            $totals = $scoped()->selectRaw(
                'COUNT(*) AS total, '
                .'COALESCE(SUM(amount), 0) AS billed, '
                .'COALESCE(SUM(paid_total), 0) AS paid, '
                .'COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) AS balance, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS paid_count',
                [Invoice::STATUS_PAGADA]
            )->first();
            $data['summary'] = [
                'total' => (int) $totals->total,
                'billed' => (float) $totals->billed,
                'paid' => (float) $totals->paid,
                'balance' => (float) $totals->balance,
                'paid_count' => (int) $totals->paid_count,
                'unpaid_count' => (int) $totals->total - (int) $totals->paid_count,
                'overdue_count' => $scoped()->overdue()->count(),
            ];
        } else {
            $data['payments'] = Payment::query()
                ->with(['invoice.associate', 'registeredBy'])
                ->whereHas('invoice.associate', $associateScope)
                ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term, $associateSearch) {
                    $w->whereHas('invoice', fn ($i) => $i->where('receipt_number', 'like', "%{$term}%"))
                        ->orWhereHas('invoice.associate', $associateSearch);
                }))
                ->when($filters['invoice_id'], fn ($q) => $q->where('invoice_id', $filters['invoice_id']))
                ->when($filters['date_from'], fn ($q) => $q->whereDate('paid_at', '>=', $filters['date_from']))
                ->when($filters['date_to'], fn ($q) => $q->whereDate('paid_at', '<=', $filters['date_to']))
                ->when($filters['state'] === 'validos', fn ($q) => $q->active())
                ->when($filters['state'] === 'anulados', fn ($q) => $q->voided())
                ->when($filters['method'], fn ($q) => $filters['method'] === 'sin_metodo'
                    ? $q->whereNull('method')
                    : $q->where('method', $filters['method']))
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
        }

        return view('payments.index', $data);
    }

    public function create(Request $request): View
    {
        $invoices = Invoice::query()
            ->with('associate')
            ->whereRaw(Invoice::BALANCE_SQL.' > 0')
            ->whereNull('voided_at')
            ->orderBy('due_date')
            ->get();

        // "Registrar pago" on a row of the Cuotas list preselects that
        // invoice (?invoice_id=); the toolbar button opens the form blank.
        $selectedInvoiceId = $request->integer('invoice_id') ?: null;

        // Same ajax/full-page branch every other modal-based form in this
        // app uses (js-modal-link opens the bare form as an overlay; a
        // direct URL visit or no-JS fallback gets the full page).
        return $request->ajax()
            ? view('payments._form', compact('invoices', 'selectedInvoiceId'))
            : view('payments.create', compact('invoices', 'selectedInvoiceId'));
    }

    public function storeQuick(PaymentQuickRequest $request, PaymentService $service): RedirectResponse
    {
        $data = $request->validated();
        $invoice = Invoice::findOrFail($data['invoice_id']);

        try {
            $payment = $service->register(
                invoice: $invoice,
                amount: (float) $data['amount'],
                paidAt: Carbon::parse($data['paid_at']),
                registeredBy: $request->user()->id,
                notes: $data['notes'] ?? null,
                method: $data['method'],
                operationNumber: $data['operation_number'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        AuditLog::record('payment.register', 'payment', (string) $payment->id, 'success', [
            'invoice_id' => $invoice->id,
            'amount' => $data['amount'],
        ]);

        return redirect()->route('payments.index')->with('success', 'Pago registrado correctamente.');
    }

    public function store(PaymentRequest $request, Invoice $invoice, PaymentService $service): RedirectResponse
    {
        $data = $request->validated();

        try {
            $payment = $service->register(
                invoice: $invoice,
                amount: (float) $data['amount'],
                paidAt: Carbon::parse($data['paid_at']),
                registeredBy: $request->user()->id,
                notes: $data['notes'] ?? null,
                method: $data['method'],
                operationNumber: $data['operation_number'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        AuditLog::record('payment.register', 'payment', (string) $payment->id, 'success', [
            'invoice_id' => $invoice->id,
            'amount' => $data['amount'],
        ]);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Pago registrado correctamente.');
    }

    public function void(PaymentVoidRequest $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $data = $request->validated();

        try {
            $service->void($payment, $data['reason'], $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        AuditLog::record('payment.void', 'payment', (string) $payment->id, 'success', [
            'invoice_id' => $payment->invoice_id,
            'reason' => $data['reason'],
        ]);

        $invoice = $payment->invoice()->with('associate')->first();
        Notification::record(
            type: Notification::TYPE_PAYMENT_VOIDED,
            title: "Pago anulado — {$invoice->associate->name}",
            message: "{$invoice->period} — {$data['reason']}",
            entityType: 'payment',
            entityId: (string) $payment->id,
            link: route('invoices.show', $payment->invoice_id),
        );

        return redirect()->route('invoices.show', $payment->invoice_id)->with('success', 'Pago anulado correctamente.');
    }

    /**
     * Every toolbar filter, normalized: '' → null, unknown option → null,
     * so the view and the queries never see a value that isn't one of
     * the dropdown's own.
     *
     * @return array<string, mixed>
     */
    private function filtersFrom(Request $request): array
    {
        $pick = fn (string $key, array $allowed) => in_array((string) $request->query($key, ''), $allowed, true) ? (string) $request->query($key) : null;
        $text = fn (string $key) => trim((string) $request->query($key, '')) !== '' ? trim((string) $request->query($key)) : null;
        $year = $request->query('year');
        $month = $request->query('month');

        return [
            'q' => trim((string) $request->query('q', '')),
            'associate_id' => $request->integer('associate_id') ?: null,
            'invoice_id' => $request->integer('invoice_id') ?: null,
            'status' => $pick('status', array_keys(self::STATUS_FILTERS)),
            'year' => preg_match('/^\d{4}$/', (string) $year) ? (string) $year : null,
            'month' => ctype_digit((string) $month) && (int) $month >= 1 && (int) $month <= 12 ? (int) $month : null,
            'sectorista' => $text('sectorista'),
            'category' => $text('category'),
            'date_from' => $text('date_from'),
            'date_to' => $text('date_to'),
            'state' => $pick('state', array_keys(self::MOVEMENT_FILTERS)),
            'method' => $pick('method', [...array_keys(Payment::METHODS), 'sin_metodo']),
        ];
    }

    /**
     * Dropdown options come from what is actually stored so the user
     * never picks a value that returns nothing.
     *
     * @return array<string, array<int, string>>
     */
    private function filterOptions(): array
    {
        $distinct = fn (string $column) => Associate::query()
            ->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->all();

        $years = Invoice::query()
            ->selectRaw('DISTINCT SUBSTR(period, 1, 4) AS year')
            ->orderByDesc('year')
            ->pluck('year')
            ->all();

        return [
            'years' => $years,
            'sectorista' => $distinct('sectorista'),
            'category' => $distinct('category'),
        ];
    }
}
