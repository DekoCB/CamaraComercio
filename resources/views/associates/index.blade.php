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

    @include('associates._tabs', ['active' => 'listado'])

    <div class="table-card">
        @php
            $filterLabels = [
                'q' => 'Búsqueda',
                'status' => 'Estado',
                'sectorista' => 'Sectorista',
                'category' => 'Categoría',
                'person_type' => 'Tipo de persona',
                'billing_district' => 'Distrito',
                'associate_id' => 'Asociado',
            ];
            $activeFilters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
            $pretty = fn ($key, $value) => $key === 'status' ? ucfirst(strtolower($value)) : $value;
        @endphp

        <div class="table-toolbar">
            <form class="filter-bar filter-bar-grow" method="GET" action="{{ route('associates.index') }}">
                <div class="search-input search-input-grow">
                    {{ icon('search', 'icon', 16) }}
                    <input type="search" name="q" class="form-control" placeholder="Buscar por razón social, RUC, nombre comercial, correo o representante" value="{{ $term }}">
                </div>

                @foreach (['status' => 'Estado', 'sectorista' => 'Sectorista', 'category' => 'Categoría', 'person_type' => 'Tipo de persona', 'billing_district' => 'Distrito'] as $key => $label)
                    @if ($key === 'status' || $filterOptions[$key] !== [])
                        <select name="{{ $key }}" class="form-select form-select-sm" aria-label="{{ $label }}" onchange="this.form.submit()">
                            <option value="">{{ $label }}: todos</option>
                            @foreach ($filterOptions[$key] as $option)
                                <option value="{{ $option }}" {{ ($filters[$key] ?? '') === $option ? 'selected' : '' }}>{{ $pretty($key, $option) }}</option>
                            @endforeach
                        </select>
                    @endif
                @endforeach

                @if (isset($filters['associate_id']) && $filters['associate_id'])
                    <input type="hidden" name="associate_id" value="{{ $filters['associate_id'] }}">
                @endif

                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                @if ($activeFilters)
                    <a href="{{ route('associates.index') }}" class="btn btn-link btn-sm">Limpiar</a>
                @endif
            </form>
        </div>

        @if ($activeFilters)
            <div class="filter-chips" aria-label="Filtros activos">
                <span class="filter-chips-label">{{ $associates->total() }} resultado{{ $associates->total() === 1 ? '' : 's' }} con:</span>
                @foreach ($activeFilters as $key => $value)
                    <a href="{{ route('associates.index', array_diff_key($activeFilters, [$key => true])) }}" class="filter-chip" title="Quitar este filtro">
                        <span class="filter-chip-key">{{ $filterLabels[$key] ?? $key }}</span>
                        {{ $pretty($key, $value) }}
                        {{ icon('x', 'icon', 12) }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($associates->isEmpty())
            <x-empty-state icon="users" title="No hay asociados registrados"
                :message="$activeFilters ? 'No se encontraron resultados para los filtros seleccionados.' : 'Comienza registrando el primer asociado de la Cámara.'">
                @can('associates.manage')
                    @if (! $activeFilters)
                        <a href="{{ route('associates.create') }}" class="btn btn-primary btn-sm">{{ icon('plus', 'icon', 16) }} Nuevo asociado</a>
                    @endif
                @endcan
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Razón social</th>
                        <th>RUC</th>
                        <th>Nombre comercial</th>
                        <th>Sectorista</th>
                        <th>Cat.</th>
                        <th class="is-numeric">Monto</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($associates as $associate)
                        <tr>
                            <td class="cell-primary cell-clamp">
                                <a href="{{ route('associates.show', $associate) }}" class="link-plain">{{ $associate->name }}</a>
                            </td>
                            <td class="cell-muted cell-nowrap">{{ $associate->ruc ?? '-' }}</td>
                            <td class="cell-muted cell-clamp">{{ $associate->company ?? '-' }}</td>
                            <td class="cell-muted cell-nowrap">{{ $associate->sectorista ?? '-' }}</td>
                            <td class="cell-muted">{{ $associate->category ?? '-' }}</td>
                            <td class="is-numeric cell-muted cell-nowrap">{{ $associate->monthly_fee !== null ? 'S/ '.number_format((float) $associate->monthly_fee, 2) : '-' }}</td>
                            <td class="cell-muted cell-email" title="{{ $associate->email }}">{{ $associate->email ?? '-' }}</td>
                            <td>
                                @include('associates._status_badge', ['status' => $associate->status])
                            </td>
                            <td class="is-numeric">
                                <div class="row-actions">
                                    <a href="{{ route('associates.show', $associate) }}" class="btn btn-ghost btn-icon" title="Ver ficha" aria-label="Ver ficha">
                                        {{ icon('eye', 'icon', 16) }}
                                    </a>
                                    @can('billing.view')
                                        <a href="{{ route('invoices.index', ['associate_id' => $associate->id]) }}" class="btn btn-ghost btn-icon" title="Ver facturas" aria-label="Ver facturas">
                                            {{ icon('file-text', 'icon', 16) }}
                                        </a>
                                    @endcan
                                    @can('associates.manage')
                                        <a href="{{ route('associates.edit', $associate) }}" class="btn btn-ghost btn-icon" title="Editar" aria-label="Editar">
                                            {{ icon('pencil', 'icon', 16) }}
                                        </a>
                                        <form method="POST" action="{{ route('associates.destroy', $associate) }}"
                                              data-confirm="¿Eliminar al asociado &quot;{{ $associate->name }}&quot;? Esta acción no se puede deshacer."
                                              data-confirm-title="¿Eliminar asociado?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-ghost-danger btn-icon" title="Eliminar" aria-label="Eliminar">
                                                {{ icon('trash-2', 'icon', 16) }}
                                            </button>
                                        </form>
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
