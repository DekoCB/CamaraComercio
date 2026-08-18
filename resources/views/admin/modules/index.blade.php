@extends('layouts.app')

@section('title', 'Módulos')

@section('content')
    <x-page-header title="Administración" subtitle="Usuarios, roles y módulos del sistema.">
        <x-slot:actions>
            <a href="{{ route('admin.modules.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Nuevo módulo">{{ icon('plus', 'icon', 16) }} Nuevo módulo</a>
        </x-slot:actions>
    </x-page-header>

    @include('admin._nav')

    <div class="table-card">
        @if ($modules->isEmpty())
            <x-empty-state icon="grid-3x3" title="Aún no hay módulos" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th class="is-numeric">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($modules as $module)
                        <tr>
                            <td><code style="font-size: 0.8rem; color: var(--color-text-secondary);">{{ $module->code }}</code></td>
                            <td class="cell-primary">{{ $module->name }}</td>
                            <td>
                                @if ($module->is_active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-neutral">Inactivo</span>
                                @endif
                            </td>
                            <td class="is-numeric">
                                <form method="POST" action="{{ route('admin.modules.toggle', $module) }}" class="d-inline"
                                      @if ($module->is_active) data-confirm="¿Desactivar el módulo &quot;{{ $module->name }}&quot;? Desaparecerá del menú para todos los roles que lo tengan asignado." data-confirm-title="¿Desactivar módulo?" @endif>
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_active" value="{{ $module->is_active ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-ghost btn-sm">
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
