<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleAccessRequest;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\AuditLog;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'role' => null,
            'action' => route('admin.roles.store'),
            'method' => 'POST',
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create($request->validated());

        AuditLog::record('role.create', 'role', (string) $role->id);

        return redirect()->route('admin.roles.access.edit', $role)
            ->with('success', 'Rol creado correctamente. Ahora asigne sus permisos y módulos.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', [
            'role' => $role,
            'action' => route('admin.roles.update', $role),
            'method' => 'PUT',
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        AuditLog::record('role.update', 'role', (string) $role->id);

        return redirect()->route('admin.roles.index')->with('success', 'Rol actualizado correctamente.');
    }

    public function editAccess(Role $role): View
    {
        return view('admin.roles.access', [
            'role' => $role,
            'permissions' => Permission::orderBy('code')->get(),
            'assignedPermissionIds' => $role->permissions()->pluck('permissions.id')->all(),
            'modules' => Module::orderBy('sort_order')->orderBy('name')->get(),
            'assignedModuleIds' => $role->modules()->pluck('modules.id')->all(),
        ]);
    }

    public function updateAccess(RoleAccessRequest $request, Role $role): RedirectResponse
    {
        $role->permissions()->sync($request->validated('permissions', []));
        $role->modules()->sync($request->validated('modules', []));

        AuditLog::record('role.update_access', 'role', (string) $role->id);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Permisos y módulos actualizados. Los usuarios con este rol deberán volver a iniciar sesión para ver los cambios.');
    }
}
