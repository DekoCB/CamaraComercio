<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Section 15 of the functional spec: bring associates already tracked
 * in Excel into the system. The flow is deliberately two steps —
 * parse/validate (preview) then import (confirm) — so nothing is
 * written until the user has seen exactly what will happen, per the
 * spec's explicit requirement that nothing gets inserted unvalidated.
 *
 * The header aliases follow the Cámara's master workbook ("DATA DE
 * ASOCIADOS ..."): row 1 carries the headers, row 2 the month labels of
 * the contributions grid ("AÑO 2024-APORTES", "AÑO 2025", …), and each
 * associate then spans two rows — the first with its master data plus
 * the comprobante number under every month it paid, the second (no
 * master data) with the amount paid under the same columns. Those two
 * rows become one invoice + one payment per paid month, so the Pagos
 * module reflects the Excel history the moment the file is imported.
 *
 * Re-importing the same workbook is safe: an associate already in the
 * database (matched by RUC, or by razón social when neither side has a
 * RUC) is updated instead of rejected, and only the months it doesn't
 * have an invoice for yet are added.
 */
class AssociateImportService
{
    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    /**
     * Recognized header names (case/accent-insensitive, trailing digits
     * ignored — the workbook numbers repeated headers like "DNI N°4" or
     * "CORREO 6"), mapped to the associates.* column they fill. When the
     * same header appears twice (legal representative vs. representative
     * before the CCH), the second occurrence is requested with `#2`.
     * Only "razón social"/"nombre" is required; a file missing it is
     * rejected outright rather than silently importing blank names.
     */
    private const COLUMN_ALIASES = [
        'name' => ['razon social', 'nombre', 'name', 'asociado'],
        'status' => ['estado', 'status'],
        'sectorista' => ['sectorista'],
        'category' => ['cat', 'categoria'],
        'monthly_fee' => ['monto a pagar', 'monto', 'cuota'],
        'joined_at' => ['fecha de ingreso', 'ingreso'],
        'person_type' => ['tipo de persona'],
        'anniversary_date' => ['fecha de aniversario', 'aniversario'],
        'ruc' => ['ruc'],
        'company' => ['nombre comercial', 'empresa', 'company', 'compania'],
        'contact_phone' => ['contacto', 'telefono', 'telefono de la empresa', 'phone', 'contact_phone'],
        'email' => ['correo de la empresa', 'correo electronico', 'email'],
        'billing_address' => ['direccion de facturacion', 'direccion'],
        'billing_district' => ['distrito'],
        'mailing_address' => ['direccion de correspondencia'],
        'mailing_district' => ['distrito de correspondencia'],
        'company_size' => ['segun su tamano', 'tamano'],
        'activity_type' => ['segun su actividad', 'actividad'],
        'sector_committee' => ['comite sectorial'],
        'ciiu' => ['ciiu'],
        'sub_sector' => ['sub sector', 'subsector'],
        'legal_rep_name' => ['representante legal'],
        'legal_rep_dni' => ['dni n', 'dni'],
        'legal_rep_gender' => ['genero'],
        'legal_rep_birthday' => ['cumpleanos'],
        'legal_rep_phone' => ['celular'],
        'legal_rep_email' => ['correo'],
        'cch_rep_name' => ['respresentante ante la cch', 'representante ante la cch', 'representante cch'],
        'cch_rep_dni' => ['dni n#2', 'dni#2'],
        'cch_rep_gender' => ['genero#2'],
        'cch_rep_birthday' => ['cumpleanos#2'],
        'cch_rep_phone' => ['celular#2'],
        'cch_rep_email' => ['correo#2'],
        'notes' => ['observaciones', 'observacion', 'notas'],
    ];

    private const DATE_FIELDS = ['joined_at', 'anniversary_date', 'legal_rep_birthday', 'cch_rep_birthday'];

    /**
     * Cell contents in the contributions grid that mean "no payment for
     * this month" even though the cell isn't empty.
     */
    private const GRID_MARKERS = ['NOTA DE CREDITO', 'ANULADO', 'EXCEPCION'];

    private const MONTH_NAMES = [
        'ene' => 1, 'jan' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'apr' => 4, 'may' => 5,
        'jun' => 6, 'jul' => 7, 'ago' => 8, 'aug' => 8, 'sep' => 9, 'set' => 9, 'oct' => 10,
        'nov' => 11, 'dic' => 12, 'dec' => 12,
    ];

    public function __construct(private readonly PaymentService $payments) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, columnsFound: bool}
     */
    public function parse(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        // Raw values (no number formatting) so dates arrive as Excel
        // serials and amounts as plain numbers, whatever display format
        // the workbook uses.
        $data = $sheet->toArray(null, true, false, false);

        if (empty($data)) {
            return ['rows' => [], 'columnsFound' => false];
        }

        $headerRow = array_map(fn ($h) => $this->normalizeHeader((string) $h), array_shift($data));
        $columnIndex = $this->mapColumns($headerRow);

        if (! isset($columnIndex['name'])) {
            return ['rows' => [], 'columnsFound' => false];
        }

        // Row 2 of the master workbook labels each month column of the
        // contributions grid. Detected by content (Excel date serials or
        // "Jan-24"-style labels), not by position, so a simpler file
        // without the grid still imports as plain master data.
        $rowNumber = 1; // header was row 1
        $monthColumns = [];
        if (isset($data[0]) && ! $this->hasMappedData($data[0], $columnIndex)) {
            $monthColumns = $this->detectMonthColumns($data[0]);
            if ($monthColumns !== []) {
                array_shift($data);
                $rowNumber++;
            }
        }
        $data = array_values($data);

        $existing = Associate::query()->get(['id', 'name', 'ruc', 'email']);
        $emailOwners = [];   // lower(email) => associate id
        $rucOwners = [];     // ruc => associate id
        $nameOwners = [];    // lower(name) => associate id (only associates without a RUC)
        foreach ($existing as $associate) {
            if ($associate->email !== null) {
                $emailOwners[strtolower($associate->email)] = $associate->id;
            }
            if ($associate->ruc !== null) {
                $rucOwners[$associate->ruc] = $associate->id;
            } else {
                $nameOwners[mb_strtolower($associate->name)] = $associate->id;
            }
        }
        $rucsInFile = [];

        $rows = [];
        foreach ($data as $i => $line) {
            $rowNumber++;

            // Entirely blank line for our purposes (spreadsheet tail or an
            // amounts row of the grid) — skip silently, not an error row.
            if (! $this->hasMappedData($line, $columnIndex)) {
                continue;
            }

            $raw = [];
            foreach ($columnIndex as $field => $position) {
                $raw[$field] = $line[$position] ?? null;
            }

            [$record, $errors] = $this->buildRecord($raw);

            if ($record['name'] === null) {
                $errors[] = 'La razón social es obligatoria.';
            }

            // Existing associate → update. RUC is the reliable key; the
            // razón social is only used when neither side has a RUC.
            $existingId = null;
            if ($record['ruc'] !== null) {
                if (isset($rucsInFile[$record['ruc']])) {
                    $errors[] = "El RUC {$record['ruc']} se repite en el archivo (fila {$rucsInFile[$record['ruc']]}).";
                }
                $existingId = $rucOwners[$record['ruc']] ?? null;
            } elseif ($record['name'] !== null) {
                $existingId = $nameOwners[mb_strtolower($record['name'])] ?? null;
            }

            if ($record['email'] !== null) {
                $owner = $emailOwners[strtolower($record['email'])] ?? null;
                if ($owner !== null && $owner !== $existingId) {
                    $errors[] = 'Ya existe un asociado con ese correo.';
                }
            }

            // The amounts row of the grid is the very next line and has no
            // master data of its own; anything else means this associate
            // has no amounts row (or the file has no grid at all).
            $next = $data[$i + 1] ?? null;
            $amountsLine = ($next !== null && ! $this->hasMappedData($next, $columnIndex)) ? $next : [];
            $contributions = $monthColumns === [] ? [] : $this->parseContributions($line, $amountsLine, $monthColumns, $record['monthly_fee']);

            $rows[] = [
                'row' => $rowNumber,
                'errors' => $errors,
                'action' => $existingId !== null ? self::ACTION_UPDATE : self::ACTION_CREATE,
                'existing_id' => $existingId,
                'contributions' => $contributions,
            ] + $record;

            // Guard against re-flagging the same identifier twice within
            // the same file as "already exists" once it's queued for import.
            if ($errors === []) {
                if ($record['email'] !== null) {
                    $emailOwners[strtolower($record['email'])] = $existingId ?? -$rowNumber;
                }
                if ($record['ruc'] !== null) {
                    $rucsInFile[$record['ruc']] = $rowNumber;
                }
            }
        }

        return ['rows' => $rows, 'columnsFound' => true];
    }

    /**
     * @param  array<int, array<string, mixed>>  $validRows
     * @return array{created: int, updated: int, invoices: int, payments: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(array $validRows, int $registeredBy): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'invoices' => 0, 'payments' => 0, 'errors' => []];

        foreach ($validRows as $row) {
            try {
                DB::transaction(function () use ($row, $registeredBy, &$summary) {
                    $attributes = array_intersect_key($row, array_flip(array_keys(self::COLUMN_ALIASES)));

                    if (! empty($row['existing_id'])) {
                        $associate = Associate::findOrFail($row['existing_id']);
                        // The workbook is the master record, but an empty
                        // cell there shouldn't wipe what was captured in
                        // the app (photo, phone, notes…).
                        $associate->fill(array_filter($attributes, fn ($v) => $v !== null))->save();
                        $summary['updated']++;
                    } else {
                        $associate = Associate::create($attributes + ['status' => Associate::STATUS_ACTIVO]);
                        $summary['created']++;
                    }

                    [$invoices, $payments] = $this->syncContributions($associate, $row['contributions'] ?? [], $registeredBy);
                    $summary['invoices'] += $invoices;
                    $summary['payments'] += $payments;
                });
            } catch (Throwable $e) {
                $summary['errors'][] = ['row' => $row['row'], 'message' => $e->getMessage()];
            }
        }

        return $summary;
    }

    /**
     * One invoice + one payment per paid month of the grid. Months that
     * already have an invoice are only topped up: the comprobante is
     * recorded if it was missing and, if the invoice still has a balance,
     * the Excel amount is applied to it through the same transactional
     * path a manual payment takes.
     *
     * @param  array<int, array{period: string, receipt: ?string, amount: float}>  $contributions
     * @return array{0: int, 1: int} invoices created, payments registered
     */
    private function syncContributions(Associate $associate, array $contributions, int $registeredBy): array
    {
        if ($contributions === []) {
            return [0, 0];
        }

        $invoicesCreated = 0;
        $paymentsCreated = 0;
        $byPeriod = Invoice::where('associate_id', $associate->id)
            ->whereIn('period', array_column($contributions, 'period'))
            ->get()->keyBy('period');

        foreach ($contributions as $contribution) {
            $periodStart = Carbon::createFromFormat('Y-m-d', $contribution['period'].'-01')->startOfDay();
            $invoice = $byPeriod->get($contribution['period']);

            if ($invoice === null) {
                $invoice = Invoice::create([
                    'associate_id' => $associate->id,
                    'period' => $contribution['period'],
                    'receipt_number' => $contribution['receipt'],
                    'amount' => $contribution['amount'],
                    'paid_total' => 0,
                    'issue_date' => $periodStart,
                    'due_date' => $periodStart->copy()->endOfMonth(),
                    'status' => Invoice::STATUS_PENDIENTE,
                    'created_by' => $registeredBy,
                ]);
                $invoicesCreated++;
            } elseif ($invoice->receipt_number === null && $contribution['receipt'] !== null) {
                $invoice->update(['receipt_number' => $contribution['receipt']]);
            }

            $amount = min($invoice->balance(), $contribution['amount']);
            if ($amount <= 0) {
                continue;
            }

            // The workbook doesn't record the day the fee was paid, only
            // the month it covers: date the payment at the start of that
            // period, never in the future (advance payments for months
            // still to come were collected by now, whatever the period).
            $this->payments->register(
                invoice: $invoice,
                amount: $amount,
                paidAt: $periodStart->isFuture() ? now() : $periodStart,
                registeredBy: $registeredBy,
                notes: 'Importado del padrón Excel'.($contribution['receipt'] !== null ? " — comprobante {$contribution['receipt']}" : ''),
            );
            $paymentsCreated++;
        }

        return [$invoicesCreated, $paymentsCreated];
    }

    /**
     * @param  array<int, mixed>  $receiptsLine  the associate's own row
     * @param  array<int, mixed>  $amountsLine  the row right below it (may be empty)
     * @param  array<int, string>  $monthColumns  column position => YYYY-MM
     * @return array<int, array{period: string, receipt: ?string, amount: float}>
     */
    private function parseContributions(array $receiptsLine, array $amountsLine, array $monthColumns, ?string $monthlyFee): array
    {
        $contributions = [];

        foreach ($monthColumns as $position => $period) {
            $receiptText = Str::of((string) ($receiptsLine[$position] ?? ''))->squish()->value();
            $amountRaw = $amountsLine[$position] ?? null;

            $receipt = null;
            if ($receiptText !== '') {
                if ($this->isGridMarker($receiptText)) {
                    continue;
                }
                $receipt = mb_substr($receiptText, 0, 60);
            }

            $amount = $this->parseGridAmount($amountRaw);
            if ($amount === null) {
                $amountText = trim((string) $amountRaw);
                // A comprobante with a blank amount cell is still a paid
                // month — the fee is the associate's monthly fee. Any other
                // text (ANULADO, EXCEPCION…) means it wasn't collected.
                if ($receipt === null || $amountText !== '' || $monthlyFee === null) {
                    continue;
                }
                $amount = (float) $monthlyFee;
            }

            $contributions[] = ['period' => $period, 'receipt' => $receipt, 'amount' => round($amount, 2)];
        }

        return $contributions;
    }

    /**
     * "75", "S/ 75.00", "50 Y 75" (two receipts in one month → their sum).
     */
    private function parseGridAmount(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value > 0 ? (float) $value : null;
        }

        $text = trim((string) $value);
        if ($text === '' || $this->isGridMarker($text)) {
            return null;
        }

        preg_match_all('/\d+(?:[.,]\d+)?/', $text, $matches);
        $total = 0.0;
        foreach ($matches[0] as $number) {
            $total += (float) str_replace(',', '.', $number);
        }

        return $total > 0 ? $total : null;
    }

    private function isGridMarker(string $text): bool
    {
        $needle = Str::of($text)->upper()->ascii()->value();
        foreach (self::GRID_MARKERS as $marker) {
            if (str_contains($needle, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $line
     * @return array<int, string> column position => YYYY-MM
     */
    private function detectMonthColumns(array $line): array
    {
        $columns = [];
        foreach ($line as $position => $value) {
            $period = $this->parseMonthLabel($value);
            if ($period !== null) {
                $columns[$position] = $period;
            }
        }

        return $columns;
    }

    /**
     * Excel serial (the workbook stores real dates formatted "ene-24"),
     * "Jan-24", "ene-2024", "2024-01", "01/2024" or a full date string.
     */
    private function parseMonthLabel(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            if ($value < 20000 || $value > 80000) {
                return null;
            }
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m');
            } catch (Throwable) {
                return null;
            }
        }

        $text = Str::of((string) $value)->lower()->ascii()->squish()->value();
        if ($text === '') {
            return null;
        }

        if (preg_match('/^(\d{4})[-\/](\d{1,2})(?:[-\/]\d{1,2})?$/', $text, $m)) {
            return $this->period((int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^(\d{1,2})[-\/](\d{4})$/', $text, $m)) {
            return $this->period((int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $text, $m)) {
            return $this->period((int) $m[3], (int) $m[2]);
        }
        if (preg_match('/^([a-z]{3,10})\.?[\s\-\/]*(\d{2}|\d{4})$/', $text, $m)) {
            $month = self::MONTH_NAMES[substr($m[1], 0, 3)] ?? null;
            if ($month === null) {
                return null;
            }
            $year = (int) $m[2];

            return $this->period($year < 100 ? 2000 + $year : $year, $month);
        }

        return null;
    }

    private function period(int $year, int $month): ?string
    {
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return null;
        }

        return sprintf('%04d-%02d', $year, $month);
    }

    /**
     * @param  array<int, mixed>  $line
     * @param  array<string, int>  $columnIndex
     */
    private function hasMappedData(array $line, array $columnIndex): bool
    {
        foreach ($columnIndex as $position) {
            $value = $line[$position] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalizes one spreadsheet line into associates.* attributes and
     * collects the validation messages shown in the preview.
     *
     * @param  array<string, mixed>  $raw
     * @return array{0: array<string, mixed>, 1: string[]}
     */
    private function buildRecord(array $raw): array
    {
        $errors = [];
        $record = [];

        foreach (array_keys(self::COLUMN_ALIASES) as $field) {
            $value = $raw[$field] ?? null;
            $text = $value === null ? '' : trim((string) $value);

            $record[$field] = match (true) {
                in_array($field, self::DATE_FIELDS, true) => $this->parseDate($value, $field, $errors),
                $field === 'monthly_fee' => $this->parseAmount($text, $errors),
                $field === 'ruc' => $this->parseRuc($value, $errors),
                $field === 'status' => $this->parseCatalog($text, Associate::STATUSES, 'Estado', $errors) ?? Associate::STATUS_ACTIVO,
                $field === 'person_type' => $this->parseCatalog($text, Associate::PERSON_TYPES, 'Tipo de persona', $errors),
                $field === 'legal_rep_gender', $field === 'cch_rep_gender' => $this->parseCatalog($text, Associate::GENDERS, 'Género', $errors),
                $field === 'email', $field === 'legal_rep_email', $field === 'cch_rep_email' => $this->parseEmail($text, $errors),
                $field === 'legal_rep_dni', $field === 'cch_rep_dni', $field === 'legal_rep_phone', $field === 'cch_rep_phone', $field === 'contact_phone' => $this->parseDigits($value),
                default => $text !== '' ? $text : null,
            };
        }

        return [$record, array_values(array_unique($errors))];
    }

    private function parseDate(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                // fall through to the string formats below
            }
        }

        $text = trim((string) $value);
        foreach (['d/m/Y', 'j/n/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $text);
                if ($date !== false && $date->format($format) === $text) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
                continue;
            }
        }

        $labels = [
            'joined_at' => 'La fecha de ingreso',
            'anniversary_date' => 'La fecha de aniversario',
            'legal_rep_birthday' => 'El cumpleaños del representante legal',
            'cch_rep_birthday' => 'El cumpleaños del representante ante la CCH',
        ];
        $errors[] = ($labels[$field] ?? 'La fecha')." no es válida ({$text}).";

        return null;
    }

    private function parseAmount(string $text, array &$errors): ?string
    {
        if ($text === '') {
            return null;
        }

        // "S/ 75.00", "S/.75", "75,00" … keep digits, dot and comma.
        $clean = preg_replace('/[^0-9.,-]/', '', $text) ?? '';
        if (substr_count($clean, ',') === 1 && ! str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        if ($clean === '' || ! is_numeric($clean) || (float) $clean < 0) {
            $errors[] = "El monto a pagar no es válido ({$text}).";

            return null;
        }

        return number_format((float) $clean, 2, '.', '');
    }

    private function parseRuc(mixed $value, array &$errors): ?string
    {
        $digits = $this->parseDigits($value);
        if ($digits === null) {
            return null;
        }

        if (! preg_match('/^\d{11}$/', $digits)) {
            $errors[] = "El RUC debe tener 11 dígitos ({$digits}).";

            return null;
        }

        return $digits;
    }

    /**
     * Numeric-looking identifiers (RUC, DNI, phones) come out of Excel as
     * numbers; render them without exponent/decimals and drop spacing.
     */
    private function parseDigits(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = is_float($value) || is_int($value) ? sprintf('%.0f', $value) : trim((string) $value);
        $text = preg_replace('/\s+/', '', $text) ?? $text;

        return $text !== '' ? $text : null;
    }

    private function parseEmail(string $text, array &$errors): ?string
    {
        // Stray trailing punctuation ("correo@dominio.com;") is common in
        // hand-maintained sheets — not worth rejecting the whole row.
        $text = rtrim($text, ';,. ');
        if ($text === '' || $text === '-') {
            return null;
        }
        if (! filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "El correo no es válido ({$text}).";

            return null;
        }

        return $text;
    }

    /**
     * @param  string[]  $allowed
     */
    private function parseCatalog(string $text, array $allowed, string $label, array &$errors): ?string
    {
        if ($text === '' || $text === '-') {
            return null;
        }

        $needle = Str::of($text)->lower()->ascii()->squish()->value();
        foreach ($allowed as $option) {
            if ($needle === Str::of($option)->lower()->ascii()->squish()->value()) {
                return $option;
            }
        }

        $errors[] = "{$label} no reconocido ({$text}). Valores válidos: ".implode(', ', $allowed).'.';

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::of($header)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();

        // "dni n 4", "correo 6" → "dni n", "correo"
        return trim(preg_replace('/\s*\d+$/', '', $header) ?? $header);
    }

    /**
     * @param  string[]  $headerRow
     * @return array<string, int>
     */
    private function mapColumns(array $headerRow): array
    {
        $positions = [];
        foreach ($headerRow as $position => $header) {
            if ($header !== '') {
                $positions[$header][] = $position;
            }
        }

        $index = [];
        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $occurrence = 1;
                if (str_contains($alias, '#')) {
                    [$alias, $occurrence] = explode('#', $alias);
                    $occurrence = (int) $occurrence;
                }
                $key = $this->normalizeHeader($alias);
                if (isset($positions[$key][$occurrence - 1])) {
                    $index[$field] = $positions[$key][$occurrence - 1];
                    break;
                }
            }
        }

        // A simple file with a single "Correo" header (no "Correo de la
        // empresa") means the company email: shift the plain "correo"
        // occurrences so the first one is the company's and the following
        // ones belong to the representatives.
        if (! isset($index['email']) && isset($positions['correo'])) {
            $index['email'] = $positions['correo'][0];
            unset($index['legal_rep_email'], $index['cch_rep_email']);
            if (isset($positions['correo'][1])) {
                $index['legal_rep_email'] = $positions['correo'][1];
            }
            if (isset($positions['correo'][2])) {
                $index['cch_rep_email'] = $positions['correo'][2];
            }
        }

        return $index;
    }
}
