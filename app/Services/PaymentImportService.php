<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Migración de pagos históricos desde Excel. Nunca crea la factura que
 * el pago referencia — si no existe, la fila se rechaza — y la
 * importación en sí pasa cada fila por PaymentService::register(), el
 * mismo camino transaccional que usa el registro manual de un pago,
 * para que paid_total/status de la factura queden exactamente igual de
 * consistentes que si se hubieran cargado uno por uno desde la UI.
 */
class PaymentImportService
{
    private const COLUMN_ALIASES = [
        'ruc' => ['ruc'],
        'associate' => ['asociado', 'nombre', 'associate'],
        'period' => ['periodo de factura', 'periodo', 'período', 'period'],
        'amount' => ['monto', 'amount'],
        'paid_at' => ['fecha de pago', 'fecha', 'paid_at'],
        'notes' => ['notas', 'notes', 'observaciones'],
    ];

    /**
     * @return array{rows: array<int, array<string, mixed>>, columnsFound: bool}
     */
    public function parse(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if (empty($data)) {
            return ['rows' => [], 'columnsFound' => false];
        }

        $headerRow = array_map(fn ($h) => $this->normalizeHeader((string) $h), array_shift($data));
        $columnIndex = $this->mapColumns($headerRow);

        if ((! isset($columnIndex['associate']) && ! isset($columnIndex['ruc'])) || ! isset($columnIndex['period']) || ! isset($columnIndex['amount'])) {
            return ['rows' => [], 'columnsFound' => false];
        }

        // Running total already queued against each invoice within this
        // same file — two partial-payment rows for the same factura in
        // one file must not jointly exceed its balance, and the invoice's
        // own balance() (read once, before any row is applied) wouldn't
        // reflect that on its own.
        $appliedSoFar = [];

        $rows = [];
        $rowNumber = 1;
        foreach ($data as $line) {
            $rowNumber++;

            $ruc = isset($columnIndex['ruc']) ? trim((string) ($line[$columnIndex['ruc']] ?? '')) : '';
            $associateName = isset($columnIndex['associate']) ? trim((string) ($line[$columnIndex['associate']] ?? '')) : '';
            $period = trim((string) ($line[$columnIndex['period']] ?? ''));
            $amountRaw = trim((string) ($line[$columnIndex['amount']] ?? ''));
            $paidAtRaw = isset($columnIndex['paid_at']) ? ($line[$columnIndex['paid_at']] ?? '') : '';
            $notes = isset($columnIndex['notes']) ? trim((string) ($line[$columnIndex['notes']] ?? '')) : '';

            if ($ruc === '' && $associateName === '' && $period === '' && $amountRaw === '') {
                continue;
            }

            $errors = [];

            $associate = $this->resolveAssociate($ruc, $associateName, $errors);

            if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
                $errors[] = 'El período de la factura debe tener el formato AAAA-MM.';
            }

            $amount = is_numeric(str_replace(',', '.', $amountRaw)) ? (float) str_replace(',', '.', $amountRaw) : null;
            if ($amount === null || $amount <= 0) {
                $errors[] = 'El monto debe ser un número mayor a cero.';
            }

            $paidAt = $this->parseDate($paidAtRaw);
            if ($paidAtRaw === '') {
                $errors[] = 'La fecha de pago es obligatoria.';
            } elseif ($paidAt === null) {
                $errors[] = 'La fecha de pago no es válida.';
            } elseif ($paidAt->isAfter(now())) {
                $errors[] = 'La fecha de pago no puede ser futura.';
            }

            $invoice = null;
            if ($associate && preg_match('/^\d{4}-\d{2}$/', $period)) {
                $invoice = Invoice::where('associate_id', $associate->id)->where('period', $period)->first();

                if (! $invoice) {
                    $errors[] = 'No existe una factura para ese asociado en ese período — impórtela primero.';
                } elseif ($amount !== null) {
                    $already = $appliedSoFar[$invoice->id] ?? 0.0;
                    $remaining = round($invoice->balance() - $already, 2);
                    if ($amount > $remaining) {
                        $errors[] = 'El monto supera el saldo pendiente de la factura (S/ '.number_format($remaining, 2).', considerando otros pagos de este mismo archivo).';
                    }
                }
            }

            $rows[] = [
                'row' => $rowNumber,
                'ruc' => $ruc !== '' ? $ruc : null,
                'associate_id' => $associate?->id,
                'associate_name' => $associate?->name ?? ($associateName !== '' ? $associateName : null),
                'period' => $period !== '' ? $period : null,
                'invoice_id' => $invoice?->id,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'notes' => $notes !== '' ? $notes : null,
                'errors' => $errors,
            ];

            if ($invoice && $amount !== null && $errors === []) {
                $appliedSoFar[$invoice->id] = ($appliedSoFar[$invoice->id] ?? 0.0) + $amount;
            }
        }

        return ['rows' => $rows, 'columnsFound' => true];
    }

    /**
     * @param  array<int, array<string, mixed>>  $validRows
     * @return array{created: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(array $validRows, int $registeredBy, PaymentService $paymentService): array
    {
        $created = 0;
        $errors = [];

        foreach ($validRows as $row) {
            try {
                $invoice = Invoice::findOrFail($row['invoice_id']);
                $paymentService->register(
                    invoice: $invoice,
                    amount: (float) $row['amount'],
                    paidAt: $row['paid_at'],
                    registeredBy: $registeredBy,
                    notes: $row['notes'],
                );
                $created++;
            } catch (InvalidArgumentException|Throwable $e) {
                $errors[] = ['row' => $row['row'], 'message' => $e->getMessage()];
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @param  string[]  $errors
     */
    private function resolveAssociate(string $ruc, string $name, array &$errors): ?Associate
    {
        if ($ruc !== '') {
            $associate = Associate::where('ruc', $ruc)->first();
            if (! $associate) {
                $errors[] = 'No se encontró un asociado con ese RUC.';
            }

            return $associate;
        }

        if ($name === '') {
            $errors[] = 'Debe indicar el RUC o el nombre del asociado.';

            return null;
        }

        $matches = Associate::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->get();

        if ($matches->isEmpty()) {
            $errors[] = 'No se encontró un asociado con ese nombre.';

            return null;
        }

        if ($matches->count() > 1) {
            $errors[] = 'Hay más de un asociado con ese nombre; agregue la columna RUC para identificarlo sin ambigüedad.';

            return null;
        }

        return $matches->first();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            } catch (Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->trim()->lower()->ascii()->value();
    }

    /**
     * @param  string[]  $headerRow
     * @return array<string, int>
     */
    private function mapColumns(array $headerRow): array
    {
        $index = [];
        foreach ($headerRow as $position => $header) {
            foreach (self::COLUMN_ALIASES as $field => $aliases) {
                if (isset($index[$field])) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    if ($header === Str::of($alias)->lower()->ascii()->value()) {
                        $index[$field] = $position;
                        break 2;
                    }
                }
            }
        }

        return $index;
    }
}
