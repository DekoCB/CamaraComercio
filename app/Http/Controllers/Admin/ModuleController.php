<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModuleRequest;
use App\Models\AuditLog;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function index(): View
    {
        return view('admin.modules.index', [
            'modules' => Module::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = ['action' => route('admin.modules.store')];

        return $request->ajax() ? view('admin.modules._form', $data) : view('admin.modules.form', $data);
    }

    public function store(ModuleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $module = Module::create($data);

        AuditLog::record('module.create', 'module', (string) $module->id);

        return redirect()->route('admin.modules.index')->with('success', 'Módulo creado correctamente.');
    }

    public function toggle(Request $request, Module $module): RedirectResponse
    {
        $isActive = $request->boolean('is_active');
        $module->update(['is_active' => $isActive]);

        AuditLog::record($isActive ? 'module.activate' : 'module.deactivate', 'module', (string) $module->id);

        return redirect()->route('admin.modules.index')->with('success', $isActive ? 'Módulo activado.' : 'Módulo desactivado.');
    }
}
