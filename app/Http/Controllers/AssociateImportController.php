<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AssociateImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AssociateImportController extends Controller
{
    private const SESSION_KEY = 'associate_import_path';

    public function __construct(private readonly AssociateImportService $importer) {}

    public function create(): View
    {
        return view('associates.import');
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'file.mimes' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV.',
            'file.max' => 'El archivo no puede superar los 5 MB.',
        ]);

        $storedPath = $request->file('file')->storeAs('imports', Str::uuid().'.'.$request->file('file')->extension());

        $result = $this->importer->parse(Storage::path($storedPath));

        if (! $result['columnsFound']) {
            Storage::delete($storedPath);

            return back()->withErrors([
                'file' => 'No se encontró una columna "Nombre" en el archivo. Verifique que la primera fila tenga los encabezados (Nombre, Empresa, Contacto, Correo).',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $storedPath);

        $validRows = array_filter($result['rows'], fn ($r) => $r['errors'] === []);

        return view('associates.import-preview', [
            'rows' => $result['rows'],
            'validCount' => count($validRows),
            'errorCount' => count($result['rows']) - count($validRows),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $storedPath = $request->session()->get(self::SESSION_KEY);

        if (! $storedPath || ! Storage::exists($storedPath)) {
            return redirect()->route('associates.import.create')
                ->with('error', 'La sesión de importación expiró. Vuelva a cargar el archivo.');
        }

        // Re-parse from the stored file rather than trusting anything the
        // client posted back — the preview is read-only, the stored file
        // on disk is the only source of truth for what gets imported.
        $result = $this->importer->parse(Storage::path($storedPath));
        $validRows = array_values(array_filter($result['rows'], fn ($r) => $r['errors'] === []));

        $summary = $this->importer->import($validRows);

        Storage::delete($storedPath);
        $request->session()->forget(self::SESSION_KEY);

        AuditLog::record('associate.import', 'associate', null, 'success', [
            'created' => $summary['created'],
            'skipped' => count($result['rows']) - count($validRows),
            'errors' => count($summary['errors']),
        ]);

        $skipped = count($result['rows']) - count($validRows);
        $message = "Importación completa: {$summary['created']} asociados creados";
        if ($skipped > 0) {
            $message .= ", {$skipped} omitidos por errores de validación";
        }
        if ($summary['errors'] !== []) {
            $message .= ', '.count($summary['errors']).' con error al guardar';
        }

        return redirect()->route('associates.index')->with('success', $message.'.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $storedPath = $request->session()->pull(self::SESSION_KEY);
        if ($storedPath) {
            Storage::delete($storedPath);
        }

        return redirect()->route('associates.import.create');
    }
}
