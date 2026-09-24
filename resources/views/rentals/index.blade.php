@extends('layouts.app')

@section('title', 'Alquileres')

@section('content')
    <x-page-header title="Alquileres" subtitle="Reservas de espacios de la Cámara — cotizaciones, confirmaciones y facturación.">
        <x-slot:actions>
            <a href="{{ route('rentals.calendar') }}" class="btn btn-secondary btn-sm">
                {{ icon('calendar', 'icon', 16) }} Calendario
            </a>
            @can('rentals.manage')
                <a href="{{ route('rentals.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Nueva cotización de alquiler">
                    {{ icon('plus', 'icon', 16) }} Nueva cotización
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar" method="GET" action="{{ route('rentals.index') }}" role="search">
                <select name="status" class="form-select form-select-sm" aria-label="Estado">
                    <option value="">Estado: todos</option>
                    @foreach (\App\Models\Rental::STATUS_LABELS as $key => $label)
                        <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="space_id" class="form-select form-select-sm" aria-label="Espacio">
                    <option value="">Espacio: todos</option>
                    @foreach ($spaces as $space)
                        <option value="{{ $space->id }}" {{ (int) $filters['space_id'] === $space->id ? 'selected' : '' }}>{{ $space->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                @if ($filters['status'] || $filters['space_id'])
                    <a href="{{ route('rentals.index') }}" class="btn btn-link btn-sm">Limpiar</a>
                @endif
            </form>
        </div>

        @if ($rentals->isEmpty())
            <x-empty-state icon="building-2" title="No hay alquileres"
                :message="($filters['status'] || $filters['space_id']) ? 'No se encontraron alquileres para los filtros seleccionados.' : 'Todavía no se registró ninguna cotización de alquiler.'" />
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Espacio</th>
                        <th>Asociado</th>
                        <th>Fecha</th>
                        <th class="is-numeric">Monto</th>
                        <th>Estado</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rentals as $rental)
                        <tr class="{{ $rental->isCancelled() ? 'row-voided' : '' }}">
                            <td class="cell-primary">{{ $rental->space->name }}</td>
                            <td><a href="{{ route('associates.show', $rental->associate) }}" class="link-plain">{{ $rental->associate->name }}</a></td>
                            <td class="cell-nowrap">
                                {{ $rental->starts_at->format('d/m/Y H:i') }}
                                <div class="cell-muted" style="font-size: var(--text-xs);">a {{ $rental->ends_at->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="is-numeric cell-money cell-nowrap">{{ format_money($rental->amount) }}</td>
                            <td><x-status-badge :status="$rental->status" /></td>
                            <td class="is-numeric">
                                <div class="row-actions">
                                    <a href="{{ route('rentals.show', $rental) }}" class="btn btn-ghost btn-icon" title="Ver detalle" aria-label="Ver detalle">
                                        {{ icon('eye', 'icon', 16) }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <x-pagination-meta :paginator="$rentals" noun="alquileres" />
                {{ $rentals->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
