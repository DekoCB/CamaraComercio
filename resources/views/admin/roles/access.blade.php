@extends('layouts.app')

@section('title', 'Permisos y módulos — '.$role->name)

@section('content')
    <div class="table-card" style="max-width: 760px">
        <form method="POST" action="{{ route('admin.roles.access.update', $role) }}">
            @csrf
            @method('PUT')

            <h2 class="h6">Módulos accesibles</h2>
            <p class="text-muted small">Determina qué opciones del menú puede ver este rol.</p>
            @if ($modules->isEmpty())
                <p class="text-muted small">No hay módulos creados todavía.</p>
            @else
                <div class="row mb-4">
                    @foreach ($modules as $module)
                        <div class="col-6 col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $module->id }}"
                                       id="module_{{ $module->id }}"
                                       {{ in_array($module->id, $assignedModuleIds, true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="module_{{ $module->id }}">
                                    {{ $module->name }}
                                    @unless ($module->is_active)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">inactivo</span>
                                    @endunless
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <h2 class="h6">Permisos</h2>
            <p class="text-muted small">Determina qué acciones puede ejecutar este rol (el backend valida cada una).</p>
            @if ($permissions->isEmpty())
                <p class="text-muted small">No hay permisos definidos todavía.</p>
            @else
                <div class="row mb-4">
                    @foreach ($permissions as $permission)
                        <div class="col-6 col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                       id="permission_{{ $permission->id }}"
                                       {{ in_array($permission->id, $assignedPermissionIds, true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="permission_{{ $permission->id }}">
                                    {{ $permission->description ?? $permission->code }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
