<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HU-15: shared Excel/PDF export plumbing for the Sprint 3 reports.
 * Every export gets the same header block (title, generation
 * timestamp, period) and a totals row, per the functional spec's
 * requirement that exports be self-explanatory outside the app.
 */
class ExportService
{
    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, string>|null  $totals
     * @param  array<int, array{type: 'bar'|'pie'|'line', title: string, categories: string[], series: array<string, float[]>}>  $charts  one or more charts, stacked vertically below the table
     */
    public function toExcel(string $filename, string $title, ?string $period, array $headers, array $rows, ?array $totals = null, array $charts = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', 'Generado: '.now()->format('d/m/Y H:i'));
        if ($period) {
            $sheet->setCellValue('A3', 'Período: '.$period);
        }

        $headerRow = $period ? 5 : 4;
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, $headerRow], $header);
        }
        $sheet->getStyle($headerRow.':'.$headerRow)->getFont()->setBold(true);

        $rowNumber = $headerRow + 1;
        foreach ($rows as $row) {
            foreach ($row as $i => $value) {
                $sheet->setCellValue([$i + 1, $rowNumber], $value);
            }
            $rowNumber++;
        }

        if ($totals) {
            foreach ($totals as $i => $value) {
                $sheet->setCellValue([$i + 1, $rowNumber], $value);
            }
            $sheet->getStyle($rowNumber.':'.$rowNumber)->getFont()->setBold(true);
        }

        foreach (range(1, \count($headers)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        $nextRow = $rowNumber + 2;
        foreach ($charts as $chart) {
            if ($chart['categories'] === [] || $chart['series'] === []) {
                continue;
            }
            $nextRow = $this->addChart($sheet, $chart, $nextRow);
        }

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
        ]);
    }

    /**
     * Writes a small "Categoría / Serie A / Serie B..." staging table at
     * $startRow (the data the chart plots — Excel charts always read from
     * real cells, not inline values) and anchors a native, editable chart
     * beside it. This is plain PhpSpreadsheet — the project has no
     * maatwebsite/excel dependency to lean on — so it's spelled out by
     * hand rather than hidden behind a "WithCharts" interface. A single
     * DataSeries can itself hold more than one plot (one per column here),
     * which is how a multi-line trend or a multi-series bar chart is
     * expressed — not by adding more DataSeries objects to the plot area.
     *
     * @param  array{type: 'bar'|'pie'|'line', title: string, categories: string[], series: array<string, float[]>}  $chart
     * @return int the next free row below this chart's data table, for the following chart (if any) to start at
     */
    private function addChart(Worksheet $sheet, array $chart, int $startRow): int
    {
        $categories = array_values($chart['categories']);
        $count = count($categories);

        $labelRow = $startRow;
        $dataRow = $startRow + 1;
        $lastRow = $dataRow + $count - 1;
        $sheetTitle = $sheet->getTitle();

        $sheet->setCellValue("A{$labelRow}", 'Categoría');
        foreach ($categories as $i => $category) {
            $sheet->setCellValue('A'.($dataRow + $i), $category);
        }

        // Pie charts only ever plot one series — a legend of category
        // slices, not multiple pies — so extra series are ignored there.
        $seriesMap = $chart['type'] === 'pie' ? array_slice($chart['series'], 0, 1, true) : $chart['series'];

        $plotOrder = [];
        $plotLabel = [];
        $plotCategory = [];
        $plotValues = [];
        $order = 0;
        $lastColumn = 1;
        foreach ($seriesMap as $seriesLabel => $values) {
            $columnIndex = $order + 2; // B, C, D...
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $lastColumn = $columnIndex;
            $values = array_values($values);

            $sheet->setCellValue("{$columnLetter}{$labelRow}", (string) $seriesLabel);
            foreach ($categories as $i => $category) {
                $sheet->setCellValue($columnLetter.($dataRow + $i), (float) ($values[$i] ?? 0));
            }

            $categoryRef = "'{$sheetTitle}'!\$A\${$dataRow}:\$A\${$lastRow}";
            $valueRef = "'{$sheetTitle}'!\${$columnLetter}\${$dataRow}:\${$columnLetter}\${$lastRow}";

            $plotOrder[] = $order;
            $plotLabel[$order] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!\${$columnLetter}\${$labelRow}", null, 1);
            $plotCategory[$order] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $categoryRef, null, $count);
            $plotValues[$order] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valueRef, null, $count);
            $order++;
        }

        $sheet->getStyle("A{$labelRow}:".Coordinate::stringFromColumnIndex($lastColumn).$labelRow)->getFont()->setBold(true);

        $seriesType = match ($chart['type']) {
            'pie' => DataSeries::TYPE_PIECHART,
            'line' => DataSeries::TYPE_LINECHART,
            default => DataSeries::TYPE_BARCHART,
        };

        $series = new DataSeries(
            $seriesType,
            $seriesType === DataSeries::TYPE_BARCHART ? DataSeries::GROUPING_CLUSTERED : null,
            $plotOrder,
            $plotLabel,
            $plotCategory,
            $plotValues,
        );
        if ($seriesType === DataSeries::TYPE_BARCHART) {
            $series->setPlotDirection(DataSeries::DIRECTION_COL);
        }

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $chartTitle = new ChartTitle($chart['title']);

        $chartHeight = 16;
        $chartObject = new Chart('chart_'.Str::random(8), $chartTitle, $legend, $plotArea);
        $chartObject->setTopLeftPosition('D'.$labelRow);
        $chartObject->setBottomRightPosition('L'.($labelRow + $chartHeight));

        $sheet->addChart($chartObject);

        return max($lastRow, $labelRow + $chartHeight) + 3;
    }

    public function toPdf(string $filename, string $view, array $data): Response
    {
        $options = new DompdfOptions;
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
        ]);
    }
}
