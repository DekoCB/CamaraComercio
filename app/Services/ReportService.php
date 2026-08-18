<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * HU-13/HU-14: aggregate figures for the collections and pending-debt
 * reports. Kept separate from PortfolioService (which is per-associate
 * rollups) because these are whole-portfolio snapshots for a period.
 */
class ReportService
{
    /**
     * HU-13: cash collected during a calendar month (payments.paid_at
     * falling in that month) versus what was invoiced for that period
     * (accrual). These are deliberately different bases — a payment
     * made in September can settle an August invoice — which is why
     * both figures are reported side by side instead of assuming
     * they'd match.
     */
    public function collections(string $period): array
    {
        $monthStart = CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $totalInvoiced = (float) Invoice::forPeriod($period)->sum('amount');

        $payments = Payment::whereBetween('paid_at', [$monthStart, $monthEnd]);
        $totalCollected = (float) (clone $payments)->sum('amount');
        $paymentsCount = (clone $payments)->count();
        $payingAssociatesCount = (clone $payments)
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->distinct('invoices.associate_id')
            ->count('invoices.associate_id');

        return [
            'period' => $period,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'totalInvoiced' => $totalInvoiced,
            'totalCollected' => $totalCollected,
            'paymentsCount' => $paymentsCount,
            'payingAssociatesCount' => $payingAssociatesCount,
            'payments' => (clone $payments)->with(['invoice.associate', 'registeredBy'])->orderByDesc('paid_at')->get(),
        ];
    }

    /**
     * HU-14: current snapshot of everything still owed, broken down by
     * effective status. "Vencida" here is computed with the same rule
     * as Invoice::effectiveStatus() (unpaid + due_date in the past),
     * expressed in raw SQL because this is a GROUP BY across all
     * invoices, not a single model's accessor.
     */
    public function pendingDebt(): array
    {
        $unpaid = Invoice::where('status', '!=', Invoice::STATUS_PAGADA);

        $totalPending = (float) (clone $unpaid)->selectRaw('COALESCE(SUM(amount - paid_total), 0) as total')->value('total');
        $debtorsCount = (clone $unpaid)->distinct('associate_id')->count('associate_id');
        $pendingInvoicesCount = (clone $unpaid)->count();
        $overdueInvoicesCount = Invoice::overdue()->count();

        // CURDATE() is MySQL-only; the test suite runs this same query
        // against SQLite (see docs/PROJECT_ANALYSIS.md section 10.4), so
        // "today" is bound as a parameter instead of a SQL function.
        $distribution = DB::table('invoices')
            ->where('status', '!=', Invoice::STATUS_PAGADA)
            ->selectRaw("CASE WHEN due_date < ? THEN 'VENCIDA' ELSE status END as bucket", [now()->toDateString()])
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('SUM(amount - paid_total) as total_balance')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        return [
            'totalPending' => $totalPending,
            'debtorsCount' => $debtorsCount,
            'pendingInvoicesCount' => $pendingInvoicesCount,
            'overdueInvoicesCount' => $overdueInvoicesCount,
            'distribution' => $distribution,
        ];
    }
}
