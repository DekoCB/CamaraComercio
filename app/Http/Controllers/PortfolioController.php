<?php

namespace App\Http\Controllers;

use App\Models\Associate;
use App\Models\Payment;
use App\Services\PortfolioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public const MONTHS = [
        1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    public function __construct(private readonly PortfolioService $portfolio) {}

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
