<?php

namespace App\Http\Controllers;

use App\Http\Requests\BenefitRequest;
use App\Models\AuditLog;
use App\Models\Benefit;
use App\Services\BenefitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de beneficios institucionales (oct-2026) — tercera pestaña de
 * Asociados, mismo permiso associates.manage que el resto del padrón, sin
 * módulo ni permiso propio (igual criterio que Espacios dentro de
 * Alquileres). El catálogo arranca corto a propósito: se puede ampliar
 * desde esta pantalla a medida que la Cámara confirme más beneficios.
 */
class BenefitController extends Controller
{
    public function index(BenefitService $service): View
    {
        return view('associates.benefits', [
            'benefits' => Benefit::orderBy('name')->get(),
            'summary' => $service->remainingByAssociate(),
        ]);
    }

    public function create(Request $request): View
    {
        return $request->ajax() ? view('benefits._form') : view('benefits.create');
    }

    public function store(BenefitRequest $request): RedirectResponse
    {
        $benefit = Benefit::create($request->validated() + ['is_active' => true]);

        AuditLog::record('benefit.create', 'benefit', (string) $benefit->id, 'success');

        return redirect()->route('benefits.index')->with('success', 'Beneficio creado.');
    }

    public function edit(Benefit $benefit, Request $request): View
    {
        return $request->ajax() ? view('benefits._form', ['benefit' => $benefit]) : view('benefits.edit', ['benefit' => $benefit]);
    }

    public function update(BenefitRequest $request, Benefit $benefit): RedirectResponse
    {
        $benefit->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        AuditLog::record('benefit.update', 'benefit', (string) $benefit->id, 'success');

        return redirect()->route('benefits.index')->with('success', 'Beneficio actualizado.');
    }
}
