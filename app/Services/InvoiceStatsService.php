<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregations behind "Estadísticas de facturación": everything is
 * computed in SQL over the invoices the filters select (year, month,
 * sectorista, category) so the charts stay cheap however many periods
 * the Cámara accumulates. "Vencida" is derived the same way the rest of
 * the app does it (unpaid + due date in the past), never stored.
 */
class InvoiceStatsService
{
    private const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * @param  array{year?: ?string, month?: ?int, sectorista?: ?string, category?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $base = fn (): Builder => Invoice::query()
            ->when($filters['year'] ?? null, fn ($q) => $q->where('period', 'like', $filters['year'].'-%'))
            ->when($filters['month'] ?? null, fn ($q) => $q->where('period', 'like', '%-'.str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT)))
            ->when(($filters['sectorista'] ?? null) || ($filters['category'] ?? null), fn ($q) => $q->whereHas('associate', fn ($a) => $a
                ->when($filters['sectorista'] ?? null, fn ($a) => $a->where('sectorista', $filters['sectorista']))
                ->when($filters['category'] ?? null, fn ($a) => $a->where('category', $filters['category']))));

        $today = now()->toDateString();
        $overdueSql = "status != '".Invoice::STATUS_PAGADA."' AND due_date < ?";

        return [
            'summary' => $this->summary($base(), $overdueSql, $today),
            'monthly' => $this->monthly($base(), $filters['year'] ?? null),
            'by_status' => $this->byStatus($base(), $overdueSql, $today),
            'top_debtors' => $this->topDebtors($base()),
            'by_sectorista' => $this->bySectorista($base()),
        ];
    }

    /** @return array<string, float|int> */
    private function summary(Builder $query, string $overdueSql, string $today): array
    {
        $row = $query->selectRaw(
            'COUNT(*) AS total, '
            .'COALESCE(SUM(amount), 0) AS billed, '
            .'COALESCE(SUM(paid_total), 0) AS paid, '
            .'COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) AS balance, '
            .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS paid_count, '
            .'COALESCE(SUM(CASE WHEN '.$overdueSql.' THEN 1 ELSE 0 END), 0) AS overdue_count, '
            .'COALESCE(SUM(CASE WHEN '.$overdueSql.' THEN '.Invoice::BALANCE_SQL.' ELSE 0 END), 0) AS overdue_balance, '
            .'COUNT(DISTINCT associate_id) AS associates',
            [Invoice::STATUS_PAGADA, $today, $today]
        )->first();

        $billed = (float) $row->billed;

        return [
            'total' => (int) $row->total,
            'billed' => $billed,
            'paid' => (float) $row->paid,
            'balance' => (float) $row->balance,
            'paid_count' => (int) $row->paid_count,
            'unpaid_count' => (int) $row->total - (int) $row->paid_count,
            'overdue_count' => (int) $row->overdue_count,
            'overdue_balance' => (float) $row->overdue_balance,
            'associates' => (int) $row->associates,
            'collection_rate' => $billed > 0 ? round((float) $row->paid / $billed * 100, 1) : 0.0,
        ];
    }

    /**
     * One point per period. With a year selected the twelve months are
     * always present (zeros included) so the chart reads as a calendar.
     *
     * @return array<int, array{period: string, label: string, billed: float, paid: float, balance: float, count: int}>
     */
    private function monthly(Builder $query, ?string $year): array
    {
        $rows = $query->selectRaw(
            'period, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS billed, COALESCE(SUM(paid_total), 0) AS paid, COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) AS balance'
        )->groupBy('period')->orderBy('period')->get()->keyBy('period');

        $periods = $year
            ? array_map(fn (int $m) => sprintf('%s-%02d', $year, $m), range(1, 12))
            : $rows->keys()->all();

        return array_map(function (string $period) use ($rows) {
            $row = $rows->get($period);
            [$y, $m] = explode('-', $period);

            return [
                'period' => $period,
                'label' => (self::MONTHS[(int) $m - 1] ?? $m).' '.substr($y, 2),
                'billed' => (float) ($row->billed ?? 0),
                'paid' => (float) ($row->paid ?? 0),
                'balance' => (float) ($row->balance ?? 0),
                'count' => (int) ($row->count ?? 0),
            ];
        }, $periods);
    }

    /** @return array<string, array{count: int, amount: float}> */
    private function byStatus(Builder $query, string $overdueSql, string $today): array
    {
        $rows = $query->selectRaw(
            'CASE WHEN '.$overdueSql." THEN '".Invoice::STATUS_VENCIDA."' ELSE status END AS effective_status, "
            .'COUNT(*) AS count, COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) AS balance, COALESCE(SUM(amount), 0) AS amount',
            [$today]
        )->groupBy('effective_status')->get()->keyBy('effective_status');

        $result = [];
        foreach ([Invoice::STATUS_PAGADA, Invoice::STATUS_PARCIAL, Invoice::STATUS_PENDIENTE, Invoice::STATUS_VENCIDA] as $status) {
            $row = $rows->get($status);
            $result[$status] = [
                'count' => (int) ($row->count ?? 0),
                'amount' => (float) ($row->amount ?? 0),
                'balance' => (float) ($row->balance ?? 0),
            ];
        }

        return $result;
    }

    /** @return array<int, array{name: string, balance: float, count: int}> */
    private function topDebtors(Builder $query, int $limit = 10): array
    {
        // Qualified: associates has its own `status` column since the
        // master-data expansion, so the unpaid() scope would be ambiguous.
        return $query->where('invoices.status', '!=', Invoice::STATUS_PAGADA)
            ->join('associates', 'associates.id', '=', 'invoices.associate_id')
            ->selectRaw('associates.name AS name, COUNT(*) AS count, COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) AS balance')
            ->groupBy('associates.id', 'associates.name')
            ->orderByDesc('balance')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'balance' => (float) $r->balance, 'count' => (int) $r->count])
            ->all();
    }

    /** @return array<int, array{name: string, billed: float, paid: float, balance: float, count: int}> */
    private function bySectorista(Builder $query): array
    {
        return $query
            ->join('associates', 'associates.id', '=', 'invoices.associate_id')
            ->selectRaw('associates.sectorista AS sectorista, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS billed, COALESCE(SUM(paid_total), 0) AS paid, COALESCE(SUM('.Invoice::BALANCE_SQL.'), 0) AS balance')
            ->groupBy('associates.sectorista')
            ->orderByDesc('billed')
            ->get()
            ->map(fn ($r) => ['name' => $r->sectorista ?: 'Sin sectorista', 'billed' => (float) $r->billed, 'paid' => (float) $r->paid, 'balance' => (float) $r->balance, 'count' => (int) $r->count])
            ->all();
    }

    /** @return string[] */
    public static function availableYears(): array
    {
        return Invoice::query()->selectRaw('DISTINCT SUBSTR(period, 1, 4) AS year')->orderByDesc('year')->pluck('year')->all();
    }

    /** @return string[] */
    public static function distinctAssociateValues(string $column): array
    {
        return Associate::query()->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column)->pluck($column)->all();
    }
}
