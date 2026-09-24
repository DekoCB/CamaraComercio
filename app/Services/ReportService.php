<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Protest;
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
     * HU-13: cash collected during a period (payments.paid_at falling in
     * it) versus what was invoiced for the same period (accrual). These
     * are deliberately different bases — a payment made in September can
     * settle an August invoice — which is why both figures are reported
     * side by side instead of assuming they'd match.
     *
     * A single calendar month is still the default (and what the "Período"
     * picker in the UI drives); passing $dateFrom/$dateTo widens both
     * bases to an arbitrary range instead — "facturado" then sums every
     * invoice period the range touches, "cobrado" every payment inside
     * the exact dates given.
     */
    public function collections(string $period, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $isRange = $dateFrom !== null && $dateTo !== null;
        [$rangeStart, $rangeEnd] = $this->resolveRange($period, $dateFrom, $dateTo);

        $totalInvoiced = (float) Invoice::query()
            ->whereNull('voided_at')
            ->whereBetween('period', [$rangeStart->format('Y-m'), $rangeEnd->format('Y-m')])
            ->sum('amount');

        $payments = Payment::active()->whereBetween('paid_at', [$rangeStart, $rangeEnd]);
        $totalCollected = (float) (clone $payments)->sum('amount');
        $paymentsCount = (clone $payments)->count();
        $payingAssociatesCount = (clone $payments)
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->distinct('invoices.associate_id')
            ->count('invoices.associate_id');

        return [
            'period' => $period,
            'dateFrom' => $isRange ? $rangeStart->toDateString() : null,
            'dateTo' => $isRange ? $rangeEnd->toDateString() : null,
            'isRange' => $isRange,
            'monthStart' => $rangeStart,
            'monthEnd' => $rangeEnd,
            'totalInvoiced' => $totalInvoiced,
            'totalCollected' => $totalCollected,
            'paymentsCount' => $paymentsCount,
            'payingAssociatesCount' => $payingAssociatesCount,
            'payments' => (clone $payments)->with(['invoice.associate', 'registeredBy'])->orderByDesc('paid_at')->get(),
            'trend' => $this->monthlyTrend($rangeEnd),
        ];
    }

    /**
     * Facturado vs. cobrado for the 6 calendar months up to and including
     * $through's month — context for the single period/range collections()
     * already reports, not a replacement for it. Same accrual-vs-cash split
     * as collections(), just repeated per month instead of summed once.
     *
     * @return array{labels: string[], invoiced: float[], collected: float[]}
     */
    public function monthlyTrend(CarbonImmutable $through, int $months = 6): array
    {
        $labels = [];
        $invoiced = [];
        $collected = [];

        $monthNames = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $through->startOfMonth()->subMonths($months - 1 - $i);
            $monthEnd = $monthStart->endOfMonth();

            $labels[] = $monthNames[$monthStart->month].' '.$monthStart->format('y');
            $invoiced[] = (float) Invoice::query()->whereNull('voided_at')->where('period', $monthStart->format('Y-m'))->sum('amount');
            $collected[] = (float) Payment::active()->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount');
        }

        return ['labels' => $labels, 'invoiced' => $invoiced, 'collected' => $collected];
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
        $unpaid = Invoice::where('status', '!=', Invoice::STATUS_PAGADA)->whereNull('voided_at');

        $totalPending = (float) (clone $unpaid)->selectRaw('COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) as total')->value('total');
        $debtorsCount = (clone $unpaid)->distinct('associate_id')->count('associate_id');
        $pendingInvoicesCount = (clone $unpaid)->count();
        $overdueInvoicesCount = Invoice::overdue()->count();

        // CURDATE() is MySQL-only; the test suite runs this same query
        // against SQLite (see docs/PROJECT_ANALYSIS.md section 10.4), so
        // "today" is bound as a parameter instead of a SQL function.
        $distribution = DB::table('invoices')
            ->where('status', '!=', Invoice::STATUS_PAGADA)
            ->whereNull('voided_at')
            ->selectRaw("CASE WHEN due_date < ? THEN 'VENCIDA' ELSE status END as bucket", [now()->toDateString()])
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('SUM('.Invoice::BALANCE_SQL.') as total_balance')
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

    /**
     * "Productividad por cobrador" — payments grouped by the user who
     * registered them (payments.registered_by), same period/range picker
     * as collections() above. Unassigned payments (imports predating this
     * column, or a seeded/legacy row with no registered_by) are grouped
     * under a single "Sin asignar" bucket rather than dropped, so the
     * total always reconciles with totalCollected.
     */
    public function collectorProductivity(string $period, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $isRange = $dateFrom !== null && $dateTo !== null;
        [$rangeStart, $rangeEnd] = $this->resolveRange($period, $dateFrom, $dateTo);

        $payments = Payment::active()->whereBetween('paid_at', [$rangeStart, $rangeEnd]);

        $totalCollected = (float) (clone $payments)->sum('amount');
        $paymentsCount = (clone $payments)->count();

        $byCollector = (clone $payments)
            ->leftJoin('users', 'users.id', '=', 'payments.registered_by')
            ->selectRaw('payments.registered_by AS user_id, COALESCE(users.name, ?) AS name, COUNT(*) AS count, COALESCE(SUM(payments.amount), 0) AS total', ['Sin asignar'])
            ->groupBy('payments.registered_by', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id,
                'name' => $r->name,
                'count' => (int) $r->count,
                'total' => (float) $r->total,
                'average' => (int) $r->count > 0 ? round((float) $r->total / (int) $r->count, 2) : 0.0,
                'share' => $totalCollected > 0 ? round((float) $r->total / $totalCollected * 100, 1) : 0.0,
            ])
            ->all();

        return [
            'period' => $period,
            'dateFrom' => $isRange ? $rangeStart->toDateString() : null,
            'dateTo' => $isRange ? $rangeEnd->toDateString() : null,
            'isRange' => $isRange,
            'monthStart' => $rangeStart,
            'monthEnd' => $rangeEnd,
            'totalCollected' => $totalCollected,
            'paymentsCount' => $paymentsCount,
            'collectorsCount' => count($byCollector),
            'byCollector' => $byCollector,
        ];
    }

    /**
     * "Registro de Protestos y Moras" — cuántos se registraron en el
     * período (registered_at, no created_at, para que un registro
     * cargado tarde igual cuente en el mes al que corresponde), con el
     * mismo desglose por tipo/vía que Protest::TYPES/CHANNELS. "Cantidad
     * producida al mes" del acta del cliente — ver ProtestService para
     * el resumen del mes en curso que usa el propio módulo de Protestos.
     */
    public function protests(string $period, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $isRange = $dateFrom !== null && $dateTo !== null;
        [$rangeStart, $rangeEnd] = $this->resolveRange($period, $dateFrom, $dateTo);

        $records = Protest::query()
            ->with('associate')
            ->whereBetween('registered_at', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->orderByDesc('registered_at')
            ->get();

        $totalAmount = (float) $records->sum('amount');

        $byType = collect(Protest::TYPES)->map(fn ($label, $key) => [
            'label' => $label,
            'count' => $records->where('type', $key)->count(),
            'total' => (float) $records->where('type', $key)->sum('amount'),
        ])->all();

        $byChannel = collect(Protest::CHANNELS)->map(fn ($label, $key) => [
            'label' => $label,
            'count' => $records->where('channel', $key)->count(),
        ])->all();

        return [
            'period' => $period,
            'dateFrom' => $isRange ? $rangeStart->toDateString() : null,
            'dateTo' => $isRange ? $rangeEnd->toDateString() : null,
            'isRange' => $isRange,
            'monthStart' => $rangeStart,
            'monthEnd' => $rangeEnd,
            'totalCount' => $records->count(),
            'totalAmount' => $totalAmount,
            'regularizedCount' => $records->where('status', Protest::STATUS_REGULARIZADO)->count(),
            'byType' => $byType,
            'byChannel' => $byChannel,
            'records' => $records,
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function resolveRange(string $period, ?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom !== null && $dateTo !== null) {
            return [CarbonImmutable::parse($dateFrom)->startOfDay(), CarbonImmutable::parse($dateTo)->endOfDay()];
        }

        $start = CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();

        return [$start, $start->endOfMonth()];
    }
}
