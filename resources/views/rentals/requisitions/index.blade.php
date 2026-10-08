@extends('layouts.app')

@section('title', 'Requerimientos de pago')

@section('content')
    <x-page-header title="Requerimientos de pago" subtitle="Requerimientos de pago a proveedores y reembolsos de gastos de Logística.">
        <x-slot:actions>
            <a href="{{ route('rentals.requisitions.create') }}" class="btn btn-primary btn-sm">
                {{ icon('plus', 'icon', 16) }} Nuevo requerimiento
            </a>
        </x-slot:actions>
    </x-page-header>

    @include('rentals._tabs', ['active' => 'requerimientos'])

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar" method="GET" action="{{ route('rentals.requisitions.index') }}" role="search">
                <select name="type" class="form-select form-select-sm" aria-label="Tipo">
                    <option value="">Tipo: todos</option>
                    @foreach (\App\Models\PaymentRequisition::TYPES as $key => $label)
                        <option value="{{ $key }}" {{ $filters['type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                @if ($filters['type'])
                    <a href="{{ route('rentals.requisitions.index') }}" class="btn btn-link btn-sm">Limpiar</a>
                @endif
            </form>
        </div>

        @if ($records->isEmpty())
            <x-empty-state icon="receipt" title="No hay requerimientos"
                :message="$filters['type'] ? 'No se encontraron requerimientos para el filtro seleccionado.' : 'Todavía no se registró ningún requerimiento de pago.'" />
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>N°</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Asunto</th>
                        <th>Titular</th>
                        <th class="is-numeric">Monto</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td class="cell-nowrap">{{ $record->documentNumber() }}</td>
                            <td class="cell-nowrap">{{ format_date($record->issued_at) }}</td>
                            <td>{{ $record->typeLabel() }}</td>
                            <td class="cell-clamp">{{ $record->subject }}</td>
                            <td class="cell-clamp">{{ $record->beneficiary_name }}</td>
                            <td class="is-numeric cell-money cell-nowrap">{{ format_money($record->total()) }}</td>
                            <td class="is-numeric">
                                <div class="row-actions">
                                    <a href="{{ route('rentals.requisitions.show', $record) }}" class="btn btn-ghost btn-icon" title="Ver detalle" aria-label="Ver detalle">
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
                <x-pagination-meta :paginator="$records" noun="requerimientos" />
                {{ $records->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
