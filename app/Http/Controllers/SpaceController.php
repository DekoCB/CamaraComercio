<?php

namespace App\Http\Controllers;

use App\Http\Requests\SpaceRequest;
use App\Models\AuditLog;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de espacios alquilables — pantalla chica anidada en Alquileres
 * (mismo permiso rentals.manage, sin módulo ni permiso propio) en vez de
 * su propia sección, ya que nada más en el sistema usa este catálogo.
 */
class SpaceController extends Controller
{
    public function index(): View
    {
        return view('spaces.index', ['spaces' => Space::orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        return $request->ajax() ? view('spaces._form') : view('spaces.create');
    }

    public function store(SpaceRequest $request): RedirectResponse
    {
        $space = Space::create($request->validated() + ['is_active' => true]);

        AuditLog::record('space.create', 'space', (string) $space->id, 'success');

        return redirect()->route('spaces.index')->with('success', 'Espacio creado.');
    }

    public function edit(Space $space, Request $request): View
    {
        return $request->ajax() ? view('spaces._form', ['space' => $space]) : view('spaces.edit', ['space' => $space]);
    }

    public function update(SpaceRequest $request, Space $space): RedirectResponse
    {
        $space->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        AuditLog::record('space.update', 'space', (string) $space->id, 'success');

        return redirect()->route('spaces.index')->with('success', 'Espacio actualizado.');
    }
}
