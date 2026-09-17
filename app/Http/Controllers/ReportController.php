<?php

namespace App\Http\Controllers;

use App\Services\ExportService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ExportService $export,
    ) {}

    public function index(): View
    {
        return view('reports.index', ['defaultPeriod' => now()->format('Y-m')]);
    }

    public function collections(Request $request): View
    {
        $period = $request->query('period', now()->format('Y-m'));
        [$dateFrom, $dateTo] = $this->dateRangeFrom($request);

        return view('reports.collections', $this->reports->collections($period, $dateFrom, $dateTo));
    }

    public function debt(): View
    {
        return view('reports.debt', $this->reports->pendingDebt());
    }

    public function collectors(Request $request): View
    {
        $period = $request->query('period', now()->format('Y-m'));
        [$dateFrom, $dateTo] = $this->dateRangeFrom($request);

        return view('reports.collectors', $this->reports->collectorProductivity($period, $dateFrom, $dateTo));
    }

    public function exportCollections(Request $request, string $format): StreamedResponse|Response
    {
        $period = $request->query('period', now()->format('Y-m'));
        [$dateFrom, $dateTo] = $this->dateRangeFrom($request);
        $data = $this->reports->collections($period, $dateFrom, $dateTo);
        $label = $data['isRange'] ? "{$data['dateFrom']}_{$data['dateTo']}" : $period;

        $headers = ['Fecha', 'Asociado', 'Período factura', 'Monto', 'Registrado por'];
        $rows = $data['payments']->map(fn ($p) => [
            $p->paid_at->format('d/m/Y'),
            $p->invoice->associate->name,
            $p->invoice->period,
            number_format((float) $p->amount, 2),
            $p->registeredBy->name ?? '-',
        ])->all();
        $totals = ['', '', 'Total cobrado', number_format($data['totalCollected'], 2), ''];

        if ($format === 'pdf') {
            return $this->export->toPdf("cobranza-{$label}", 'reports.pdf.collections', $data);
        }

        return $this->export->toExcel("cobranza-{$label}", 'Cobranza del período', $data['isRange'] ? "{$data['dateFrom']} a {$data['dateTo']}" : $period, $headers, $rows, $totals);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function dateRangeFrom(Request $request): array
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        return $dateFrom && $dateTo ? [$dateFrom, $dateTo] : [null, null];
    }

    public function exportDebt(string $format): StreamedResponse|Response
    {
        $data = $this->reports->pendingDebt();

        $headers = ['Estado', 'Facturas', 'Saldo'];
        $rows = $data['distribution']->map(fn ($row) => [
            $row->bucket,
            $row->invoice_count,
            number_format((float) $row->total_balance, 2),
        ])->values()->all();
        $totals = ['Total', $data['pendingInvoicesCount'], number_format($data['totalPending'], 2)];

        if ($format === 'pdf') {
            return $this->export->toPdf('deuda-pendiente', 'reports.pdf.debt', $data);
        }

        return $this->export->toExcel('deuda-pendiente', 'Deuda pendiente', null, $headers, $rows, $totals);
    }

    public function exportCollectors(Request $request, string $format): StreamedResponse|Response
    {
        $period = $request->query('period', now()->format('Y-m'));
        [$dateFrom, $dateTo] = $this->dateRangeFrom($request);
        $data = $this->reports->collectorProductivity($period, $dateFrom, $dateTo);
        $label = $data['isRange'] ? "{$data['dateFrom']}_{$data['dateTo']}" : $period;

        $headers = ['Cobrador', 'Pagos', 'Total cobrado', 'Promedio por pago', '% del total'];
        $rows = array_map(fn (array $row) => [
            $row['name'],
            $row['count'],
            number_format($row['total'], 2),
            number_format($row['average'], 2),
            $row['share'].'%',
        ], $data['byCollector']);
        $totals = ['Total', $data['paymentsCount'], number_format($data['totalCollected'], 2), '', ''];

        if ($format === 'pdf') {
            return $this->export->toPdf("productividad-{$label}", 'reports.pdf.collectors', $data);
        }

        return $this->export->toExcel("productividad-{$label}", 'Productividad por cobrador', $data['isRange'] ? "{$data['dateFrom']} a {$data['dateTo']}" : $period, $headers, $rows, $totals);
    }
}
