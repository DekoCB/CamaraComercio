<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * HU-10/HU-11/HU-12: read-only aggregate queries over associates'
 * invoices/payments. Kept out of the controller because the
 * with*() aggregate wiring (and the HAVING clause for "only
 * associates who owe something") is non-trivial and reused across
 * two endpoints (the general portfolio and the debtors list).
 */
class PortfolioService
{
    public const STATUS_FILTERS = [
        'con_deuda' => 'Con deuda',
        'con_vencidas' => 'Con facturas vencidas',
        'al_dia' => 'Sin deuda',
    ];

    /**
     * HU-10: every associate with their invoiced/paid/pending totals
     * and pending/overdue invoice counts, regardless of whether they
     * currently owe anything.
     *
     * @param  array{q?: ?string, status?: ?string, sectorista?: ?string, category?: ?string}  $filters
     */
    public function debtSummary(array $filters = []): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->when($filters['status'] ?? null, fn (Builder $q) => match ($filters['status']) {
                'con_deuda' => $q->whereHas('invoices', fn (Builder $i) => $i->where('status', '!=', Invoice::STATUS_PAGADA)),
                'con_vencidas' => $q->whereHas('invoices', fn (Builder $i) => $i->overdue()),
                'al_dia' => $q->whereDoesntHave('invoices', fn (Builder $i) => $i->where('status', '!=', Invoice::STATUS_PAGADA)),
                default => $q,
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Totals for the whole portfolio the filters select (not just the
     * current page) — the KPI row above the table.
     *
     * @param  array{q?: ?string, sectorista?: ?string, category?: ?string}  $filters
     * @return array<string, float|int>
     */
    public function portfolioSummary(array $filters = []): array
    {
        $associateIds = $this->baseQuery($filters)->pluck('associates.id');
        $today = now()->toDateString();

        $row = Invoice::query()
            ->whereIn('associate_id', $associateIds)
            ->selectRaw(
                'COALESCE(SUM(amount), 0) AS billed, '
                .'COALESCE(SUM(paid_total), 0) AS paid, '
                .'COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) AS balance, '
                .'COUNT(DISTINCT CASE WHEN status != ? THEN associate_id END) AS debtors, '
                .'COALESCE(SUM(CASE WHEN status != ? AND due_date < ? THEN '.Invoice::BALANCE_SQL.' ELSE 0 END), 0) AS overdue_balance',
                [Invoice::STATUS_PAGADA, Invoice::STATUS_PAGADA, $today]
            )->first();

        $billed = (float) $row->billed;

        return [
            'associates' => $associateIds->count(),
            'billed' => $billed,
            'paid' => (float) $row->paid,
            'balance' => (float) $row->balance,
            'overdue_balance' => (float) $row->overdue_balance,
            'debtors' => (int) $row->debtors,
            'collection_rate' => $billed > 0 ? round((float) $row->paid / $billed * 100, 1) : 0.0,
        ];
    }

    /**
     * HU-11: only associates who currently have a pending balance,
     * with contact info and the period their oldest unpaid invoice
     * belongs to (useful to prioritize who to call first).
     *
     * Filtered with whereHas(status != PAGADA) rather than a HAVING on
     * the total_invoiced/total_paid aggregate aliases: our own status
     * invariant (PAGADA only when paid_total >= amount) makes "has an
     * invoice that isn't PAGADA" exactly equivalent to "owes something",
     * and a bare HAVING with no GROUP BY over subquery-derived aliases is
     * accepted by MySQL but rejected by SQLite ("HAVING clause on a
     * non-aggregate query") — which the test suite runs against, per
     * docs/PROJECT_ANALYSIS.md section 10.4. whereHas is portable and
     * reads as the actual business rule besides.
     *
     * @param  array{q?: ?string, sectorista?: ?string, category?: ?string, only_overdue?: bool, sort?: ?string}  $filters
     */
    public function debtors(array $filters = []): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->withMin(['invoices as oldest_pending_period' => fn (Builder $q) => $q->where('status', '!=', Invoice::STATUS_PAGADA)], 'period')
            ->whereHas('invoices', fn (Builder $q) => $q->where('status', '!=', Invoice::STATUS_PAGADA))
            ->when($filters['only_overdue'] ?? false, fn (Builder $q) => $q->whereHas('invoices', fn (Builder $i) => $i->overdue()))
            // Biggest debt first by default (a correlated subquery rather
            // than an alias so it orders the same on MySQL and SQLite).
            ->when(
                ($filters['sort'] ?? 'balance') === 'balance',
                fn (Builder $q) => $q->orderByDesc(DB::raw('(SELECT COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) FROM invoices WHERE invoices.associate_id = associates.id)')),
                fn (Builder $q) => $q->orderBy('name')
            )
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Ledger of every payment movement across the portfolio, with the
     * filters the Cartera "Historial de pagos" tab offers.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paymentsLedger(array $filters): LengthAwarePaginator
    {
        return $this->ledgerQuery($filters)
            ->with(['invoice.associate', 'registeredBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * Totals of the ledger the filters select plus a breakdown per
     * payment method (valid payments only).
     *
     * @param  array<string, mixed>  $filters
     * @return array{count: int, total: float, voided_count: int, by_method: array<int, array{method: string, label: string, total: float, count: int}>}
     */
    public function ledgerSummary(array $filters): array
    {
        $valid = $this->ledgerQuery(array_merge($filters, ['state' => 'validos']));

        $byMethod = (clone $valid)
            ->selectRaw('method, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['method' => $r->method ?? '', 'label' => Payment::METHODS[$r->method] ?? 'Sin especificar', 'total' => (float) $r->total, 'count' => (int) $r->count])
            ->all();

        return [
            'count' => (clone $valid)->count(),
            'total' => (float) (clone $valid)->sum('amount'),
            'voided_count' => $this->ledgerQuery(array_merge($filters, ['state' => 'anulados']))->count(),
            'by_method' => $byMethod,
        ];
    }

    /**
     * HU-12: a single associate's contact info, financial summary and
     * full invoice/payment history.
     *
     * @return array<string, mixed>
     */
    public function statement(Associate $associate, ?string $year = null): array
    {
        // paid_total is already the denormalized SUM(payments.amount) for
        // each invoice (kept in sync by PaymentService), so no extra
        // aggregate over payments is needed here — just eager-load the
        // relation for the payment history table in the view.
        $invoices = $associate->invoices()
            ->with(['payments.registeredBy'])
            ->when($year, fn ($q) => $q->where('period', 'like', $year.'-%'))
            ->orderByDesc('period')
            ->get();

        $payments = $invoices
            ->flatMap(fn (Invoice $invoice) => $invoice->payments->each(fn (Payment $p) => $p->setRelation('invoice', $invoice)))
            ->sortByDesc('paid_at')
            ->values();

        return [
            'totalInvoiced' => (float) $invoices->sum('amount'),
            'totalPaid' => (float) $invoices->sum('paid_total'),
            'totalPending' => (float) $invoices->sum(fn (Invoice $invoice) => $invoice->balance()),
            'overdueCount' => $invoices->filter(fn (Invoice $invoice) => $invoice->isOverdue())->count(),
            'invoices' => $invoices,
            'payments' => $payments,
            'years' => $associate->invoices()->selectRaw('DISTINCT SUBSTR(period, 1, 4) AS year')->orderByDesc('year')->pluck('year')->all(),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function ledgerQuery(array $filters): Builder
    {
        $term = trim((string) ($filters['q'] ?? ''));

        return Payment::query()
            ->when($term !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($term) {
                $w->whereHas('invoice', fn (Builder $i) => $i->where('receipt_number', 'like', "%{$term}%"))
                    ->orWhereHas('invoice.associate', fn (Builder $a) => $a->where('name', 'like', "%{$term}%")
                        ->orWhere('ruc', 'like', "%{$term}%")
                        ->orWhere('company', 'like', "%{$term}%"));
            }))
            ->when(($filters['sectorista'] ?? null) || ($filters['category'] ?? null), fn (Builder $q) => $q->whereHas('invoice.associate', fn (Builder $a) => $a
                ->when($filters['sectorista'] ?? null, fn (Builder $a) => $a->where('sectorista', $filters['sectorista']))
                ->when($filters['category'] ?? null, fn (Builder $a) => $a->where('category', $filters['category']))))
            ->when($filters['year'] ?? null, fn (Builder $q) => $q->whereYear('paid_at', $filters['year']))
            ->when($filters['month'] ?? null, fn (Builder $q) => $q->whereMonth('paid_at', $filters['month']))
            ->when($filters['date_from'] ?? null, fn (Builder $q) => $q->whereDate('paid_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn (Builder $q) => $q->whereDate('paid_at', '<=', $filters['date_to']))
            ->when($filters['method'] ?? null, fn (Builder $q) => $filters['method'] === 'sin_metodo' ? $q->whereNull('method') : $q->where('method', $filters['method']))
            ->when(($filters['state'] ?? null) === 'validos', fn (Builder $q) => $q->active())
            ->when(($filters['state'] ?? null) === 'anulados', fn (Builder $q) => $q->voided());
    }

    /** @param  array{q?: ?string, sectorista?: ?string, category?: ?string}  $filters */
    private function baseQuery(array $filters = []): Builder
    {
        $term = trim((string) ($filters['q'] ?? ''));

        return Associate::query()
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $q2) => $q2
                ->where('name', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%")
                ->orWhere('ruc', 'like', "%{$term}%")))
            ->when($filters['sectorista'] ?? null, fn (Builder $q) => $q->where('sectorista', $filters['sectorista']))
            ->when($filters['category'] ?? null, fn (Builder $q) => $q->where('category', $filters['category']))
            ->withSum('invoices as total_invoiced', 'amount')
            ->withSum('invoices as total_paid', 'paid_total')
            ->withCount(['invoices as pending_invoices_count' => fn (Builder $q) => $q->where('status', '!=', Invoice::STATUS_PAGADA)])
            ->withCount(['invoices as overdue_invoices_count' => fn (Builder $q) => $q->overdue()]);
    }
}
