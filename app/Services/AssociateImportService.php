<?php

namespace App\Services;

use App\Models\Associate;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Section 15 of the functional spec: bring associates already tracked
 * in Excel into the system. The flow is deliberately two steps —
 * parse/validate (preview) then import (confirm) — so nothing is
 * written until the user has seen exactly what will happen, per the
 * spec's explicit requirement that nothing gets inserted unvalidated.
 */
class AssociateImportService
{
    /**
     * Recognized header names (case-insensitive, accents-insensitive),
     * mapped to the associates.* column they fill. Only "nombre" is
     * required; a file missing it is rejected outright rather than
     * silently importing blank names.
     */
    private const COLUMN_ALIASES = [
        'name' => ['nombre', 'name', 'asociado'],
        'company' => ['empresa', 'company', 'compania', 'compañia'],
        'contact_phone' => ['contacto', 'telefono', 'teléfono', 'phone', 'contact_phone'],
        'email' => ['correo', 'email', 'correo electronico', 'correo electrónico'],
    ];

    /**
     * @return array{rows: array<int, array{row: int, name: ?string, company: ?string, contact_phone: ?string, email: ?string, errors: string[]}>, columnsFound: bool}
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

        if (! isset($columnIndex['name'])) {
            return ['rows' => [], 'columnsFound' => false];
        }

        $existingEmails = Associate::whereNotNull('email')->pluck('email')
            ->map(fn ($e) => strtolower($e))->all();

        $rows = [];
        $rowNumber = 1; // header was row 1
        foreach ($data as $line) {
            $rowNumber++;

            $name = trim((string) ($line[$columnIndex['name']] ?? ''));
            $company = isset($columnIndex['company']) ? trim((string) ($line[$columnIndex['company']] ?? '')) : '';
            $phone = isset($columnIndex['contact_phone']) ? trim((string) ($line[$columnIndex['contact_phone']] ?? '')) : '';
            $email = isset($columnIndex['email']) ? trim((string) ($line[$columnIndex['email']] ?? '')) : '';

            // Entirely blank line (common at the end of a spreadsheet) — skip silently, not an error row.
            if ($name === '' && $company === '' && $phone === '' && $email === '') {
                continue;
            }

            $errors = [];
            if ($name === '') {
                $errors[] = 'El nombre es obligatorio.';
            }
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'El correo no es válido.';
            }
            if ($email !== '' && in_array(strtolower($email), $existingEmails, true)) {
                $errors[] = 'Ya existe un asociado con ese correo.';
            }

            $rows[] = [
                'row' => $rowNumber,
                'name' => $name !== '' ? $name : null,
                'company' => $company !== '' ? $company : null,
                'contact_phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'errors' => $errors,
            ];

            // Guard against re-flagging the same email twice within the
            // same file as "already exists" once it's queued for import.
            if ($email !== '' && $errors === []) {
                $existingEmails[] = strtolower($email);
            }
        }

        return ['rows' => $rows, 'columnsFound' => true];
    }

    /**
     * @param  array<int, array{name: ?string, company: ?string, contact_phone: ?string, email: ?string}>  $validRows
     * @return array{created: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(array $validRows): array
    {
        $created = 0;
        $errors = [];

        foreach ($validRows as $row) {
            try {
                Associate::create([
                    'name' => $row['name'],
                    'company' => $row['company'],
                    'contact_phone' => $row['contact_phone'],
                    'email' => $row['email'],
                    'is_active' => true,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $row['row'], 'message' => $e->getMessage()];
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::of($header)->trim()->lower()->ascii()->value();

        return $header;
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
