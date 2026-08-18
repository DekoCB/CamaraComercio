<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Aggregates the dashboard's KPIs, month-over-month trend, and the two
 * charts the design brief calls for (monthly collections, portfolio
 * distribution) — pulled out of DashboardController once it grew past
 * a handful of one-line queries, matching the app/Services/ pattern
 * documented in docs/ARCHITECTURE.md.
 */
class DashboardService
{
    public function summary(): array
    {
        $currentPeriod = now()->format('Y-m');
        $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = now()->subMonthNoOverflow()->endOfMonth();

        $billedThisPeriod = (float) Invoice::forPeriod($currentPeriod)->sum('amount');
        $collectedThisMonth = (float) Payment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $collectedLastMonth = (float) Payment::whereBetween('paid_at', [$previousMonthStart, $previousMonthEnd])->sum('amount');

        return [
            'totalAssociates' => Associate::count(),
            'billedThisPeriod' => $billedThisPeriod,
            'collectedThisMonth' => $collectedThisMonth,
            'collectedTrend' => $this->trend($collectedThisMonth, $collectedLastMonth),
            'pendingBalance' => (float) Invoice::where('status', '!=', Invoice::STATUS_PAGADA)
                ->selectRaw('COALESCE(SUM(amount - paid_total), 0) as total')->value('total'),
            'overdueCount' => Invoice::overdue()->count(),
            'monthlyCollections' => $this->monthlyCollections(),
            'portfolioDistribution' => $this->portfolioDistribution(),
        ];
    }

    /**
     * @return array{direction: string, percent: float}|null null when
     *                                                       there is nothing to compare against yet (division by zero) —
     *                                                       the view simply omits the trend badge in that case, per the
     *                                                       brief's own "cuando exista información" allowance.
     */
    private function trend(float $current, float $previous): ?array
    {
        if ($previous <= 0.0) {
            return null;
        }

        $percent = round((($current - $previous) / $previous) * 100, 1);

        return [
            'direction' => $percent >= 0 ? 'up' : 'down',
            'percent' => abs($percent),
        ];
    }

    /**
     * Last 6 calendar months (oldest first): invoiced (accrual) vs.
     * collected (cash) — feeds the "Cobranza mensual" line/area chart.
     */
    private function monthlyCollections(): array
    {
        $months = collect(range(5, 0))->map(fn (int $i) => now()->subMonthsNoOverflow($i));

        return $months->map(function (Carbon $month) {
            $period = $month->format('Y-m');

            return [
                'label' => ucfirst($month->translatedFormat('M Y')),
                'billed' => (float) Invoice::forPeriod($period)->sum('amount'),
                'collected' => (float) Payment::whereBetween('paid_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('amount'),
            ];
        })->all();
    }

    /**
     * Invoice count by effective status (the same PENDIENTE/PARCIAL/
     * PAGADA/VENCIDA rule as Invoice::effectiveStatus(), computed here
     * as a portable parameterized-date query — see ReportService for
     * why "today" is bound rather than expressed as CURDATE()) — feeds
     * the "Distribución de cartera" donut.
     */
    private function portfolioDistribution(): array
    {
        $today = now()->toDateString();

        $paid = Invoice::where('status', Invoice::STATUS_PAGADA)->count();
        $overdue = Invoice::where('status', '!=', Invoice::STATUS_PAGADA)->whereDate('due_date', '<', $today)->count();
        $partial = Invoice::where('status', Invoice::STATUS_PARCIAL)->whereDate('due_date', '>=', $today)->count();
        $pending = Invoice::where('status', Invoice::STATUS_PENDIENTE)->whereDate('due_date', '>=', $today)->count();

        return [
            'PAGADA' => $paid,
            'PARCIAL' => $partial,
            'PENDIENTE' => $pending,
            'VENCIDA' => $overdue,
        ];
    }
}
