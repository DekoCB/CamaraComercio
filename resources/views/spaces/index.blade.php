@extends('layouts.app')

@section('title', 'Espacios alquilables')

@section('content')
    <x-page-header title="Espacios alquilables" subtitle="Catálogo de espacios que se pueden reservar desde Alquileres.">
        <x-slot:actions>
            <a href="{{ route('rentals.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver a Alquileres
            </a>
            <a href="{{ route('rental-catalog-items.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('sliders-horizontal', 'icon', 16) }} Equipos y cuenta bancaria
            </a>
            <a href="{{ route('spaces.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Nuevo espacio">
                {{ icon('plus', 'icon', 16) }} Nuevo espacio
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="table-card">
        @if ($spaces->isEmpty())
            <x-empty-state icon="building-2" title="No hay espacios" message="Todavía no se registró ningún espacio alquilable." />
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="is-numeric">Tarifa por hora</th>
                        <th>Estado</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($spaces as $space)
                        <tr class="{{ $space->is_active ? '' : 'row-voided' }}">
                            <td class="cell-primary">{{ $space->name }}</td>
                            <td class="cell-muted cell-clamp">{{ $space->description ?: '-' }}</td>
                            <td class="is-numeric cell-money cell-nowrap">{{ $space->default_rate ? format_money($space->default_rate) : '-' }}</td>
                            <td>
                                @if ($space->is_active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-neutral">Inactivo</span>
                                @endif
                            </td>
                            <td class="is-numeric">
                                <div class="row-actions">
                                    <a href="{{ route('spaces.edit', $space) }}" class="btn btn-ghost btn-icon js-modal-link" data-modal-title="Editar espacio" title="Editar" aria-label="Editar">
                                        {{ icon('pencil', 'icon', 16) }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
