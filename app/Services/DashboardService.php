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

        $billedThisPeriod = (float) Invoice::forPeriod($currentPeriod)->whereNull('voided_at')->sum('amount');
        $collectedThisMonth = (float) Payment::active()->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $collectedLastMonth = (float) Payment::active()->whereBetween('paid_at', [$previousMonthStart, $previousMonthEnd])->sum('amount');

        return [
            'totalAssociates' => Associate::count(),
            'billedThisPeriod' => $billedThisPeriod,
            'collectedThisMonth' => $collectedThisMonth,
            'collectedTrend' => $this->trend($collectedThisMonth, $collectedLastMonth),
            'pendingBalance' => (float) Invoice::where('status', '!=', Invoice::STATUS_PAGADA)->whereNull('voided_at')
                ->selectRaw('COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) as total')->value('total'),
            'overdueCount' => Invoice::overdue()->count(),
            'monthlyCollections' => $this->monthlyCollections(),
            'portfolioDistribution' => $this->portfolioDistribution(),
            // Company-wide picture (associates master data + billing history)
            'associatesByStatus' => $this->associatesByStatus(),
            'yearCollectionRate' => $this->yearCollectionRate(),
            'expectedMonthlyFees' => (float) Associate::where('is_active', true)->sum('monthly_fee'),
            'associatesWithFee' => Associate::where('is_active', true)->whereNotNull('monthly_fee')->count(),
            'newAssociatesThisYear' => Associate::whereYear('joined_at', now()->year)->count(),
            'collectionRateByMonth' => $this->collectionRateByMonth(),
            'topDebtors' => $this->topDebtors(),
            'portfolioBySectorista' => $this->portfolioBySectorista(),
            'associatesByCategory' => $this->associatesByCategory(),
            'associatesByActivity' => $this->associatesByColumn('activity_type', 'Sin actividad'),
            'associatesGrowth' => $this->associatesGrowth(),
        ];
    }

    /** @return array{ACTIVO: int, SUSPENDIDO: int, DESAFILIADO: int} */
    private function associatesByStatus(): array
    {
        $counts = Associate::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return [
            Associate::STATUS_ACTIVO => (int) ($counts[Associate::STATUS_ACTIVO] ?? 0),
            Associate::STATUS_SUSPENDIDO => (int) ($counts[Associate::STATUS_SUSPENDIDO] ?? 0),
            Associate::STATUS_DESAFILIADO => (int) ($counts[Associate::STATUS_DESAFILIADO] ?? 0),
        ];
    }

    /** Percent of this year's invoiced amount already collected. */
    private function yearCollectionRate(): ?float
    {
        $row = Invoice::query()->where('period', 'like', now()->format('Y').'-%')->whereNull('voided_at')
            ->selectRaw('COALESCE(SUM(amount), 0) AS billed, COALESCE(SUM(paid_total), 0) AS paid')->first();

        return (float) $row->billed > 0 ? round((float) $row->paid / (float) $row->billed * 100, 1) : null;
    }

    /**
     * Last 12 periods (by invoice period, accrual view): how much of each
     * month's billing has been collected so far — the line that shows
     * whether collection is keeping up with invoicing.
     *
     * @return array<int, array{label: string, rate: ?float, billed: float, paid: float}>
     */
    private function collectionRateByMonth(): array
    {
        $months = collect(range(11, 0))->map(fn (int $i) => now()->subMonthsNoOverflow($i));
        $rows = Invoice::query()
            ->whereIn('period', $months->map(fn (Carbon $m) => $m->format('Y-m'))->all())
            ->whereNull('voided_at')
            ->selectRaw('period, COALESCE(SUM(amount), 0) AS billed, COALESCE(SUM(paid_total), 0) AS paid')
            ->groupBy('period')->get()->keyBy('period');

        return $months->map(function (Carbon $month) use ($rows) {
            $row = $rows->get($month->format('Y-m'));
            $billed = (float) ($row->billed ?? 0);
            $paid = (float) ($row->paid ?? 0);

            return [
                'label' => ucfirst($month->translatedFormat('M y')),
                'billed' => $billed,
                'paid' => $paid,
                'rate' => $billed > 0 ? round($paid / $billed * 100, 1) : null,
            ];
        })->all();
    }

    /** @return array<int, array{name: string, balance: float, count: int}> */
    private function topDebtors(int $limit = 8): array
    {
        return Invoice::query()
            ->where('invoices.status', '!=', Invoice::STATUS_PAGADA)
            ->whereNull('invoices.voided_at')
            ->join('associates', 'associates.id', '=', 'invoices.associate_id')
            ->selectRaw('associates.name AS name, COUNT(*) AS count, COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) AS balance')
            ->groupBy('associates.id', 'associates.name')
            ->orderByDesc('balance')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'balance' => (float) $r->balance, 'count' => (int) $r->count])
            ->all();
    }

    /** @return array<int, array{name: string, paid: float, balance: float, associates: int}> */
    private function portfolioBySectorista(): array
    {
        return Invoice::query()
            ->whereNull('invoices.voided_at')
            ->join('associates', 'associates.id', '=', 'invoices.associate_id')
            ->selectRaw('associates.sectorista AS sectorista, COUNT(DISTINCT associates.id) AS associates, COALESCE(SUM(paid_total), 0) AS paid, COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) AS balance')
            ->groupBy('associates.sectorista')
            ->orderByDesc('paid')
            ->get()
            ->map(fn ($r) => ['name' => $r->sectorista ?: 'Sin sectorista', 'paid' => (float) $r->paid, 'balance' => (float) $r->balance, 'associates' => (int) $r->associates])
            ->all();
    }

    /**
     * Associates per category with the monthly fee they add up to — the
     * expected recurring income of each tier.
     *
     * @return array<int, array{name: string, count: int, fees: float}>
     */
    private function associatesByCategory(): array
    {
        return Associate::query()
            ->where('is_active', true)
            ->selectRaw('category, COUNT(*) AS count, COALESCE(SUM(monthly_fee), 0) AS fees')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn ($r) => ['name' => $r->category ?: 'Sin categoría', 'count' => (int) $r->count, 'fees' => (float) $r->fees])
            ->all();
    }

    /** @return array<int, array{name: string, count: int}> */
    private function associatesByColumn(string $column, string $emptyLabel): array
    {
        return Associate::query()
            ->where('is_active', true)
            ->selectRaw($column.' AS value, COUNT(*) AS count')
            ->groupBy($column)
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['name' => $r->value ?: $emptyLabel, 'count' => (int) $r->count])
            ->all();
    }

    /**
     * New associates per year (from "Fecha de ingreso") with the running
     * total — grouped in PHP so it works the same on MySQL and SQLite.
     *
     * @return array<int, array{year: string, joined: int, cumulative: int}>
     */
    private function associatesGrowth(): array
    {
        $years = Associate::query()->whereNotNull('joined_at')->pluck('joined_at')
            ->map(fn ($d) => Carbon::parse($d)->format('Y'))
            ->countBy()
            ->sortKeys();

        if ($years->isEmpty()) {
            return [];
        }

        // Readable window: the last 12 years; older joins still count in
        // the running total of the first bar.
        $last = max((int) $years->keys()->last(), (int) now()->year);
        $first = max((int) $years->keys()->first(), $last - 11);
        $cumulativeBefore = (int) $years->filter(fn ($n, $year) => (int) $year < $first)->sum();
        $cumulative = $cumulativeBefore;
        $result = [];
        for ($year = $first; $year <= $last; $year++) {
            $joined = (int) ($years[(string) $year] ?? 0);
            $cumulative += $joined;
            $result[] = ['year' => (string) $year, 'joined' => $joined, 'cumulative' => $cumulative];
        }

        return $result;
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
     * Last 12 calendar months (oldest first): invoiced (accrual) vs.
     * collected (cash) — feeds the "Cobranza mensual" line/area chart.
     */
    private function monthlyCollections(): array
    {
        $months = collect(range(11, 0))->map(fn (int $i) => now()->subMonthsNoOverflow($i));

        return $months->map(function (Carbon $month) {
            $period = $month->format('Y-m');

            return [
                'label' => ucfirst($month->translatedFormat('M y')),
                'billed' => (float) Invoice::forPeriod($period)->whereNull('voided_at')->sum('amount'),
                'collected' => (float) Payment::active()->whereBetween('paid_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('amount'),
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
        $overdue = Invoice::where('status', '!=', Invoice::STATUS_PAGADA)->whereNull('voided_at')->whereDate('due_date', '<', $today)->count();
        $partial = Invoice::where('status', Invoice::STATUS_PARCIAL)->whereNull('voided_at')->whereDate('due_date', '>=', $today)->count();
        $pending = Invoice::where('status', Invoice::STATUS_PENDIENTE)->whereNull('voided_at')->whereDate('due_date', '>=', $today)->count();

        return [
            'PAGADA' => $paid,
            'PARCIAL' => $partial,
            'PENDIENTE' => $pending,
            'VENCIDA' => $overdue,
        ];
    }
}
