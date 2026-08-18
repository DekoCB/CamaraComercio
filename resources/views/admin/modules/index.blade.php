@extends('layouts.app')

@section('title', 'Módulos')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        @include('admin._nav')
        <a href="{{ route('admin.modules.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo módulo
        </a>
    </div>

    <div class="table-card">
        @if ($modules->isEmpty())
            <div class="empty-state"><i class="bi bi-grid fs-1"></i><p class="mb-0 mt-2">Aún no hay módulos.</p></div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($modules as $module)
                        <tr>
                            <td><code>{{ $module->code }}</code></td>
                            <td>{{ $module->name }}</td>
                            <td>
                                @if ($module->is_active)
                                    <span class="badge bg-success-subtle text-success-emphasis">Activo</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.modules.toggle', $module) }}" class="d-inline"
                                      @if ($module->is_active) data-confirm="¿Desactivar el módulo &quot;{{ $module->name }}&quot;? Desaparecerá del menú para todos los roles que lo tengan asignado." @endif>
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_active" value="{{ $module->is_active ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        {{ $module->is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
