@extends('layouts.app')

@section('title', 'Permisos y módulos — '.$role->name)

@section('content')
    <x-page-header title="Permisos y módulos" :subtitle="$role->name" />

    <div class="card-surface" style="max-width: 760px">
        <form method="POST" action="{{ route('admin.roles.access.update', $role) }}">
            @csrf
            @method('PUT')

            <h2 class="text-h3">Módulos accesibles</h2>
            <p class="text-secondary" style="font-size: 0.8125rem; margin-bottom: var(--space-4);">Determina qué opciones del menú puede ver este rol.</p>
            @if ($modules->isEmpty())
                <p class="text-secondary" style="font-size: 0.8125rem;">No hay módulos creados todavía.</p>
            @else
                <div class="row" style="margin-bottom: var(--space-6);">
                    @foreach ($modules as $module)
                        <div class="col-6 col-md-4">
                            <div class="form-check" style="margin-bottom: var(--space-2);">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $module->id }}"
                                       id="module_{{ $module->id }}"
                                       {{ in_array($module->id, $assignedModuleIds, true) ? 'checked' : '' }}>
                                <label for="module_{{ $module->id }}" style="font-size: 0.875rem;">
                                    {{ $module->name }}
                                    @unless ($module->is_active)
                                        <span class="badge badge-neutral">inactivo</span>
                                    @endunless
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <h2 class="text-h3">Permisos</h2>
            <p class="text-secondary" style="font-size: 0.8125rem; margin-bottom: var(--space-4);">Determina qué acciones puede ejecutar este rol (el backend valida cada una).</p>
            @if ($permissions->isEmpty())
                <p class="text-secondary" style="font-size: 0.8125rem;">No hay permisos definidos todavía.</p>
            @else
                <div class="row" style="margin-bottom: var(--space-6);">
                    @foreach ($permissions as $permission)
                        <div class="col-6 col-md-4">
                            <div class="form-check" style="margin-bottom: var(--space-2);">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                       id="permission_{{ $permission->id }}"
                                       {{ in_array($permission->id, $assignedPermissionIds, true) ? 'checked' : '' }}>
                                <label for="permission_{{ $permission->id }}" style="font-size: 0.875rem;">
                                    {{ $permission->description ?? $permission->code }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="spinner"></span>
                    <span class="btn-label-idle">{{ icon('save', 'icon', 16) }} Guardar</span>
                </button>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
