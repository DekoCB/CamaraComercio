<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * HU-10/HU-11/HU-12: read-only aggregate queries over associates'
 * invoices/payments. Kept out of the controller because the
 * with*() aggregate wiring (and the HAVING clause for "only
 * associates who owe something") is non-trivial and reused across
 * two endpoints (the general portfolio and the debtors list).
 */
class PortfolioService
{
    /**
     * HU-10: every associate with their invoiced/paid/pending totals
     * and pending/overdue invoice counts, regardless of whether they
     * currently owe anything.
     */
    public function debtSummary(?string $term = null): LengthAwarePaginator
    {
        return $this->baseQuery($term)->orderBy('name')->paginate(20)->withQueryString();
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
     */
    public function debtors(?string $term = null): LengthAwarePaginator
    {
        return $this->baseQuery($term)
            ->withMin(['invoices as oldest_pending_period' => fn (Builder $q) => $q->where('status', '!=', Invoice::STATUS_PAGADA)], 'period')
            ->whereHas('invoices', fn (Builder $q) => $q->where('status', '!=', Invoice::STATUS_PAGADA))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * HU-12: a single associate's contact info, financial summary and
     * full invoice/payment history.
     */
    public function statement(Associate $associate): array
    {
        // paid_total is already the denormalized SUM(payments.amount) for
        // each invoice (kept in sync by PaymentService), so no extra
        // aggregate over payments is needed here — just eager-load the
        // relation for the payment history table in the view.
        $invoices = $associate->invoices()
            ->with('payments')
            ->orderByDesc('period')
            ->get();

        return [
            'totalInvoiced' => (float) $invoices->sum('amount'),
            'totalPaid' => (float) $invoices->sum('paid_total'),
            'totalPending' => (float) $invoices->sum(fn (Invoice $invoice) => $invoice->balance()),
            'invoices' => $invoices,
        ];
    }

    private function baseQuery(?string $term = null): Builder
    {
        return Associate::query()
            ->when($term, fn (Builder $q) => $q->where(fn (Builder $q2) => $q2
                ->where('name', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%")))
            ->withSum('invoices as total_invoiced', 'amount')
            ->withSum('invoices as total_paid', 'paid_total')
            ->withCount(['invoices as pending_invoices_count' => fn (Builder $q) => $q->where('status', '!=', Invoice::STATUS_PAGADA)])
            ->withCount(['invoices as overdue_invoices_count' => fn (Builder $q) => $q->overdue()]);
    }
}
