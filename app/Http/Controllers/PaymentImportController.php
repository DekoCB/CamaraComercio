<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\PaymentImportService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentImportController extends Controller
{
    private const SESSION_KEY = 'payment_import_path';

    public function __construct(private readonly PaymentImportService $importer) {}

    public function create(): View
    {
        return view('payments.import');
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
                'file' => 'No se encontraron las columnas obligatorias (Asociado, Período de factura, Monto). Verifique los encabezados de la primera fila.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $storedPath);

        $validRows = array_filter($result['rows'], fn ($r) => $r['errors'] === []);

        return view('payments.import-preview', [
            'rows' => $result['rows'],
            'validCount' => count($validRows),
            'errorCount' => count($result['rows']) - count($validRows),
        ]);
    }

    public function confirm(Request $request, PaymentService $paymentService): RedirectResponse
    {
        $storedPath = $request->session()->get(self::SESSION_KEY);

        if (! $storedPath || ! Storage::exists($storedPath)) {
            return redirect()->route('payments.import.create')
                ->with('error', 'La sesión de importación expiró. Vuelva a cargar el archivo.');
        }

        $result = $this->importer->parse(Storage::path($storedPath));
        $validRows = array_values(array_filter($result['rows'], fn ($r) => $r['errors'] === []));

        $summary = $this->importer->import($validRows, $request->user()->id, $paymentService);

        Storage::delete($storedPath);
        $request->session()->forget(self::SESSION_KEY);

        AuditLog::record('payment.import', 'payment', null, 'success', [
            'created' => $summary['created'],
            'skipped' => count($result['rows']) - count($validRows),
            'errors' => count($summary['errors']),
        ]);

        $skipped = count($result['rows']) - count($validRows);
        $message = "Importación completa: {$summary['created']} pagos registrados";
        if ($skipped > 0) {
            $message .= ", {$skipped} omitidos por errores de validación";
        }
        if ($summary['errors'] !== []) {
            $message .= ', '.count($summary['errors']).' con error al guardar';
        }

        return redirect()->route('payments.index')->with('success', $message.'.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $storedPath = $request->session()->pull(self::SESSION_KEY);
        if ($storedPath) {
            Storage::delete($storedPath);
        }

        return redirect()->route('payments.import.create');
    }
}
