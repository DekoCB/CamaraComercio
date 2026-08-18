@extends('layouts.app')

@section('title', 'Asociados')

@section('content')
    <x-page-header title="Gestión de asociados" subtitle="Administra la información de los asociados de la Cámara.">
        <x-slot:actions>
            @can('associates.manage')
                <a href="{{ route('associates.import.create') }}" class="btn btn-secondary btn-sm">
                    {{ icon('upload', 'icon', 16) }} Importar desde Excel
                </a>
                <a href="{{ route('associates.create') }}" class="btn btn-primary btn-sm">
                    {{ icon('plus', 'icon', 16) }} Nuevo asociado
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="table-card">
        <div class="table-toolbar">
            <form class="search-input" method="GET" action="{{ route('associates.index') }}">
                {{ icon('search', 'icon', 16) }}
                <input type="search" name="q" class="form-control" placeholder="Buscar asociado, empresa o correo..." value="{{ $term }}">
            </form>
        </div>

        @if ($associates->isEmpty())
            <x-empty-state icon="users" title="No hay asociados registrados"
                :message="$term !== '' ? 'No se encontraron resultados para “'.$term.'”.' : 'Comienza registrando el primer asociado de la Cámara.'">
                @can('associates.manage')
                    @if ($term === '')
                        <a href="{{ route('associates.create') }}" class="btn btn-primary btn-sm">{{ icon('plus', 'icon', 16) }} Nuevo asociado</a>
                    @endif
                @endcan
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Empresa</th>
                        <th>Contacto</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th class="is-numeric">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($associates as $associate)
                        <tr>
                            <td class="cell-primary">{{ $associate->name }}</td>
                            <td class="cell-muted">{{ $associate->company ?? '-' }}</td>
                            <td class="cell-muted">{{ $associate->contact_phone ?? '-' }}</td>
                            <td class="cell-muted">{{ $associate->email ?? '-' }}</td>
                            <td>
                                @if ($associate->is_active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-neutral">Inactivo</span>
                                @endif
                            </td>
                            <td class="is-numeric">
                                <div class="d-flex gap-2 justify-content-end">
                                    @can('billing.view')
                                        <a href="{{ route('invoices.index', ['associate_id' => $associate->id]) }}" class="btn btn-ghost btn-sm" title="Ver facturas">
                                            {{ icon('file-text', 'icon', 15) }} Facturas
                                        </a>
                                    @endcan
                                    @can('associates.manage')
                                        <a href="{{ route('associates.edit', $associate) }}" class="btn btn-ghost btn-sm" title="Editar">
                                            {{ icon('pencil', 'icon', 15) }} Editar
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <x-pagination-meta :paginator="$associates" noun="asociados" />
                {{ $associates->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
