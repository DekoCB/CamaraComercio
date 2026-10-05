@extends('layouts.app')

@section('title', 'Estacionamiento')

@section('content')
    <x-page-header title="Estacionamiento" subtitle="Control de vehículos que usan el estacionamiento del local.">
        <x-slot:actions>
            <a href="{{ route('parking.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Registrar entrada">
                {{ icon('plus', 'icon', 16) }} Registrar entrada
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface mb-3">
        <h3 class="form-section-title" style="padding: 0;">Este mes</h3>
        <div class="d-flex gap-4 flex-wrap" style="margin-top: 6px;">
            <div><span class="lt-val mono" style="font-weight:700; font-size:1.25rem;">{{ $monthly['total'] }}</span> <span class="cell-muted" style="font-size:.8rem;">entradas</span></div>
            <div><span class="lt-val mono" style="font-weight:700; font-size:1.25rem;">{{ format_money($monthly['totalAmount']) }}</span> <span class="cell-muted" style="font-size:.8rem;">cobrado</span></div>
            <div><span class="lt-val mono" style="font-weight:700; font-size:1.25rem;">{{ $monthly['stillParked'] }}</span> <span class="cell-muted" style="font-size:.8rem;">estacionados ahora</span></div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar" method="GET" action="{{ route('parking.index') }}" role="search">
                <select name="status" class="form-select form-select-sm" aria-label="Estado" onchange="this.form.submit()">
                    <option value="">Estado: todos</option>
                    <option value="parked" {{ $filters['status'] === 'parked' ? 'selected' : '' }}>Estacionado</option>
                    <option value="exited" {{ $filters['status'] === 'exited' ? 'selected' : '' }}>Ya salió</option>
                </select>
                @if ($filters['status'])
                    <a href="{{ route('parking.index') }}" class="btn btn-link btn-sm">Limpiar</a>
                @endif
            </form>
        </div>

        @if ($sessions->isEmpty())
            <x-empty-state icon="car" title="No hay registros"
                :message="$filters['status'] ? 'No se encontraron registros para el filtro seleccionado.' : 'Todavía no se registró ninguna entrada al estacionamiento.'" />
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Placa</th>
                        <th>Dueño</th>
                        <th>Vehículo</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th class="is-numeric">Monto</th>
                        <th>Estado</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($sessions as $session)
                        <tr>
                            <td class="cell-primary mono">{{ $session->plate }}</td>
                            <td class="cell-clamp">{{ $session->ownerLabel() }}</td>
                            <td class="cell-clamp cell-muted">{{ $session->vehicle_description ?: '-' }}</td>
                            <td class="cell-nowrap">{{ $session->entered_at->format('d/m/Y H:i') }}</td>
                            <td class="cell-nowrap">{{ $session->exited_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td class="is-numeric cell-money cell-nowrap">{{ $session->amount ? format_money($session->amount) : '-' }}</td>
                            <td>
                                @if ($session->isParked())
                                    <span class="badge badge-success">Estacionado</span>
                                @else
                                    <span class="badge badge-neutral">Salió</span>
                                @endif
                            </td>
                            <td class="is-numeric">
                                <div class="row-actions">
                                    @if ($session->isParked())
                                        <a href="{{ route('parking.edit', $session) }}" class="btn btn-ghost btn-icon js-modal-link" data-modal-title="Editar registro" title="Editar" aria-label="Editar">
                                            {{ icon('pencil', 'icon', 16) }}
                                        </a>
                                        <a href="{{ route('parking.checkout.form', $session) }}" class="btn btn-ghost btn-icon js-modal-link" data-modal-title="Registrar salida" title="Registrar salida" aria-label="Registrar salida">
                                            {{ icon('log-out', 'icon', 16) }}
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <x-pagination-meta :paginator="$sessions" noun="registros" />
                {{ $sessions->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
