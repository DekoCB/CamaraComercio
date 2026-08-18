<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
     */
    public function toExcel(string $filename, string $title, ?string $period, array $headers, array $rows, ?array $totals = null): StreamedResponse
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

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
        ]);
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
