<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Migración de facturas históricas desde Excel — mismo patrón de dos
 * pasos (parse/validar, luego import) que AssociateImportService, por
 * la misma razón: nada se inserta hasta que el usuario vio una vista
 * previa con los errores detectados fila por fila.
 *
 * Nunca crea el asociado si no existe — a diferencia del importador de
 * asociados, este flujo asume que los asociados ya están dados de alta
 * (por Excel o manualmente) y solo trae su historial de facturación.
 */
class InvoiceImportService
{
    private const COLUMN_ALIASES = [
        'ruc' => ['ruc'],
        'associate' => ['asociado', 'nombre', 'associate'],
        'period' => ['periodo', 'período', 'period'],
        'amount' => ['monto', 'amount'],
        'issue_date' => ['fecha de emision', 'fecha de emisión', 'emision', 'emisión', 'issue_date'],
        'due_date' => ['fecha de vencimiento', 'vencimiento', 'due_date'],
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

        // Tracks period+associate combinations already queued for creation
        // within this same file — the DB unique constraint only catches
        // collisions against what's already saved, not two rows of the
        // same file both trying to invoice the same associate+período.
        $queuedPeriods = [];

        $rows = [];
        $rowNumber = 1;
        foreach ($data as $line) {
            $rowNumber++;

            $ruc = isset($columnIndex['ruc']) ? trim((string) ($line[$columnIndex['ruc']] ?? '')) : '';
            $associateName = isset($columnIndex['associate']) ? trim((string) ($line[$columnIndex['associate']] ?? '')) : '';
            $period = trim((string) ($line[$columnIndex['period']] ?? ''));
            $amountRaw = trim((string) ($line[$columnIndex['amount']] ?? ''));
            $issueDateRaw = isset($columnIndex['issue_date']) ? ($line[$columnIndex['issue_date']] ?? '') : '';
            $dueDateRaw = isset($columnIndex['due_date']) ? ($line[$columnIndex['due_date']] ?? '') : '';

            if ($ruc === '' && $associateName === '' && $period === '' && $amountRaw === '') {
                continue;
            }

            $errors = [];

            $associate = $this->resolveAssociate($ruc, $associateName, $errors);

            if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
                $errors[] = 'El período debe tener el formato AAAA-MM.';
            }

            $amount = is_numeric(str_replace(',', '.', $amountRaw)) ? (float) str_replace(',', '.', $amountRaw) : null;
            if ($amount === null || $amount <= 0) {
                $errors[] = 'El monto debe ser un número mayor a cero.';
            }

            $issueDate = $this->parseDate($issueDateRaw);
            if ($issueDateRaw !== '' && $issueDate === null) {
                $errors[] = 'La fecha de emisión no es válida.';
            }

            $dueDate = $this->parseDate($dueDateRaw);
            if ($dueDateRaw !== '' && $dueDate === null) {
                $errors[] = 'La fecha de vencimiento no es válida.';
            }
            if ($issueDate && $dueDate && $dueDate->lt($issueDate)) {
                $errors[] = 'La fecha de vencimiento no puede ser anterior a la de emisión.';
            }

            if ($associate && preg_match('/^\d{4}-\d{2}$/', $period)) {
                $key = $associate->id.'|'.$period;

                if (Invoice::where('associate_id', $associate->id)->where('period', $period)->exists()) {
                    $errors[] = 'Ya existe una factura para este asociado en este período.';
                } elseif (isset($queuedPeriods[$key])) {
                    $errors[] = 'Este asociado y período ya aparecen en otra fila de este archivo.';
                }
            }

            $rows[] = [
                'row' => $rowNumber,
                'ruc' => $ruc !== '' ? $ruc : null,
                'associate_id' => $associate?->id,
                'associate_name' => $associate?->name ?? ($associateName !== '' ? $associateName : null),
                'period' => $period !== '' ? $period : null,
                'amount' => $amount,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'errors' => $errors,
            ];

            if ($associate && preg_match('/^\d{4}-\d{2}$/', $period) && $errors === []) {
                $queuedPeriods[$associate->id.'|'.$period] = true;
            }
        }

        return ['rows' => $rows, 'columnsFound' => true];
    }

    /**
     * @param  array<int, array<string, mixed>>  $validRows
     * @return array{created: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(array $validRows, ?int $createdBy): array
    {
        $created = 0;
        $errors = [];

        foreach ($validRows as $row) {
            try {
                Invoice::create([
                    'associate_id' => $row['associate_id'],
                    'period' => $row['period'],
                    'amount' => $row['amount'],
                    'paid_total' => 0,
                    'issue_date' => $row['issue_date'] ?? Carbon::parse($row['period'].'-01'),
                    'due_date' => $row['due_date'] ?? Carbon::parse($row['period'].'-01')->endOfMonth(),
                    'status' => Invoice::STATUS_PENDIENTE,
                    'created_by' => $createdBy,
                ]);
                $created++;
            } catch (Throwable $e) {
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
