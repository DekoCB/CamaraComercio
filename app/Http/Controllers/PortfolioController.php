<?php

namespace App\Http\Controllers;

use App\Models\Associate;
use App\Models\Payment;
use App\Services\ExportService;
use App\Services\PortfolioService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioController extends Controller
{
    public const MONTHS = [
        1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    public function __construct(
        private readonly PortfolioService $portfolio,
        private readonly ExportService $export,
    ) {}

    /** Cartera por asociado: totals per associate + portfolio KPIs. */
    public function index(Request $request): View
    {
        $filters = $this->filtersFrom($request, ['q', 'status', 'sectorista', 'category']);
        if ($filters['status'] !== null && ! array_key_exists($filters['status'], PortfolioService::STATUS_FILTERS)) {
            $filters['status'] = null;
        }

        $data = [
            'associates' => $this->portfolio->debtSummary($filters),
            'summary' => $this->portfolio->portfolioSummary($filters),
            'filters' => $filters,
            'term' => $filters['q'],
            'statusFilters' => PortfolioService::STATUS_FILTERS,
            'filterOptions' => $this->filterOptions(),
        ];

        return $request->ajax() ? view('portfolio._index_results', $data) : view('portfolio.index', $data);
    }

    /** Historial de pagos: the ledger of every payment movement. */
    public function payments(Request $request): View
    {
        $filters = $this->filtersFrom($request, ['q', 'year', 'month', 'date_from', 'date_to', 'method', 'state', 'sectorista', 'category']);
        if ($filters['method'] !== null && ! in_array($filters['method'], [...array_keys(Payment::METHODS), 'sin_metodo'], true)) {
            $filters['method'] = null;
        }
        if (! in_array($filters['state'], ['validos', 'anulados', null], true)) {
            $filters['state'] = null;
        }

        $data = [
            'payments' => $this->portfolio->paymentsLedger($filters),
            'summary' => $this->portfolio->ledgerSummary($filters),
            'filters' => $filters,
            'months' => self::MONTHS,
            'methodLabels' => Payment::METHODS + ['sin_metodo' => 'Sin especificar'],
            'filterOptions' => $this->filterOptions() + [
                'payment_years' => Payment::query()->selectRaw('DISTINCT SUBSTR(paid_at, 1, 4) AS year')->orderByDesc('year')->pluck('year')->all(),
            ],
        ];

        return $request->ajax() ? view('portfolio._payments_results', $data) : view('portfolio.payments', $data);
    }

    /** A quién falta cobrar: associates with a pending balance. */
    public function debtors(Request $request): View
    {
        $filters = $this->filtersFrom($request, ['q', 'sectorista', 'category', 'sort']);
        $filters['only_overdue'] = $request->boolean('only_overdue');
        $filters['sort'] = $filters['sort'] === 'name' ? 'name' : 'balance';

        $data = [
            'associates' => $this->portfolio->debtors($filters),
            'filters' => $filters,
            'term' => $filters['q'],
            'filterOptions' => $this->filterOptions(),
        ];

        return $request->ajax() ? view('portfolio._debtors_results', $data) : view('portfolio.debtors', $data);
    }

    /** Cartera por asociado, exported with whatever filters are active. */
    public function exportIndex(Request $request, string $format): StreamedResponse|Response
    {
        $filters = $this->filtersFrom($request, ['q', 'status', 'sectorista', 'category']);
        if ($filters['status'] !== null && ! array_key_exists($filters['status'], PortfolioService::STATUS_FILTERS)) {
            $filters['status'] = null;
        }
        $associates = $this->portfolio->debtSummaryForExport($filters);

        $headers = ['Asociado', 'RUC', 'Sectorista', 'Facturado', 'Pagado', 'Pendiente', 'Cuotas pendientes', 'Cuotas vencidas'];
        $rows = $associates->map(function (Associate $a) {
            $invoiced = (float) ($a->total_invoiced ?? 0);
            $paid = (float) ($a->total_paid ?? 0);

            return [$a->name, $a->ruc ?? '-', $a->sectorista ?? '-', number_format($invoiced, 2), number_format($paid, 2), number_format($invoiced - $paid, 2), $a->pending_invoices_count, $a->overdue_invoices_count];
        })->all();
        $totalPending = $associates->sum(fn (Associate $a) => (float) ($a->total_invoiced ?? 0) - (float) ($a->total_paid ?? 0));
        $totals = ['Total', '', '', number_format($associates->sum(fn (Associate $a) => (float) ($a->total_invoiced ?? 0)), 2), number_format($associates->sum(fn (Associate $a) => (float) ($a->total_paid ?? 0)), 2), number_format($totalPending, 2), '', ''];

        if ($format === 'pdf') {
            return $this->export->toPdf('cartera-asociados', 'portfolio.pdf.index', ['associates' => $associates]);
        }

        $totalInvoicedChart = $associates->sum(fn (Associate $a) => (float) ($a->total_invoiced ?? 0));
        $totalPaidChart = $associates->sum(fn (Associate $a) => (float) ($a->total_paid ?? 0));

        $bySectorista = $associates
            ->groupBy(fn (Associate $a) => $a->sectorista ?: 'Sin asignar')
            ->map(fn ($group) => $group->sum(fn (Associate $a) => (float) ($a->total_invoiced ?? 0) - (float) ($a->total_paid ?? 0)))
            ->sortDesc();

        $charts = [
            ['type' => 'bar', 'title' => 'Facturado, pagado y pendiente', 'categories' => ['Facturado', 'Pagado', 'Pendiente'], 'series' => ['Monto' => [$totalInvoicedChart, $totalPaidChart, $totalInvoicedChart - $totalPaidChart]]],
            ['type' => 'bar', 'title' => 'Pendiente por sectorista', 'categories' => $bySectorista->keys()->all(), 'series' => ['Pendiente' => $bySectorista->values()->all()]],
        ];

        return $this->export->toExcel('cartera-asociados', 'Cartera por asociado', null, $headers, $rows, $totals, $charts);
    }

    /** A quién falta cobrar, exported with whatever filters are active. */
    public function exportDebtors(Request $request, string $format): StreamedResponse|Response
    {
        $filters = $this->filtersFrom($request, ['q', 'sectorista', 'category', 'sort']);
        $filters['only_overdue'] = $request->boolean('only_overdue');
        $associates = $this->portfolio->debtorsForExport($filters);

        $headers = ['Asociado', 'Teléfono', 'Email', 'Sectorista', 'Monto pendiente', 'Debe desde', 'Cuotas pendientes', 'Cuotas vencidas'];
        $rows = $associates->map(function (Associate $a) {
            $pending = (float) ($a->total_invoiced ?? 0) - (float) ($a->total_paid ?? 0);

            return [$a->name, $a->legal_rep_phone ?: $a->contact_phone ?: '-', $a->email ?: $a->legal_rep_email ?: '-', $a->sectorista ?? '-', number_format($pending, 2), $a->oldest_pending_period ?? '-', $a->pending_invoices_count, $a->overdue_invoices_count];
        })->all();
        $totalPending = $associates->sum(fn (Associate $a) => (float) ($a->total_invoiced ?? 0) - (float) ($a->total_paid ?? 0));
        $totals = ['Total', '', '', '', number_format($totalPending, 2), '', '', ''];

        if ($format === 'pdf') {
            return $this->export->toPdf('cartera-deudores', 'portfolio.pdf.debtors', ['associates' => $associates]);
        }

        $topDebtors = $associates
            ->map(fn (Associate $a) => ['name' => $a->name, 'pending' => (float) ($a->total_invoiced ?? 0) - (float) ($a->total_paid ?? 0)])
            ->sortByDesc('pending')
            ->take(10);
        $charts = $topDebtors->isEmpty() ? [] : [
            ['type' => 'bar', 'title' => 'Top 10 — monto pendiente', 'categories' => $topDebtors->pluck('name')->all(), 'series' => ['Pendiente' => $topDebtors->pluck('pending')->all()]],
        ];

        return $this->export->toExcel('cartera-deudores', 'A quién falta cobrar', null, $headers, $rows, $totals, $charts);
    }

    public function statement(Request $request, Associate $associate): View
    {
        $year = preg_match('/^\d{4}$/', (string) $request->query('year', '')) ? (string) $request->query('year') : null;

        return view('portfolio.statement', [
            'associate' => $associate,
            'year' => $year,
        ] + $this->portfolio->statement($associate, $year));
    }

    /**
     * @param  string[]  $keys
     * @return array<string, mixed>
     */
    private function filtersFrom(Request $request, array $keys): array
    {
        $filters = [];
        foreach ($keys as $key) {
            $value = trim((string) $request->query($key, ''));
            $filters[$key] = match ($key) {
                'q' => $value,
                'year' => preg_match('/^\d{4}$/', $value) ? $value : null,
                'month' => ctype_digit($value) && (int) $value >= 1 && (int) $value <= 12 ? (int) $value : null,
                'date_from', 'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null,
                default => $value !== '' ? $value : null,
            };
        }

        return $filters;
    }

    /** @return array<string, array<int, string>> */
    private function filterOptions(): array
    {
        $distinct = fn (string $column) => Associate::query()
            ->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->all();

        return [
            'sectorista' => $distinct('sectorista'),
            'category' => $distinct('category'),
        ];
    }
}
