@extends('layouts.app')

@section('title', 'Protestos y Moras')

@section('content')
    <x-page-header title="Protestos y Moras" subtitle="Registro de protestos y moras de títulos valores — alcance provisional, en revisión con el cliente.">
        <x-slot:actions>
            @can('protests.manage')
                <a href="{{ route('protests.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Nuevo registro de protesto o mora">
                    {{ icon('plus', 'icon', 16) }} Nuevo registro
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface mb-3">
        <h3 class="form-section-title" style="padding: 0;">Este mes</h3>
        <div class="d-flex gap-4 flex-wrap" style="margin-top: 6px;">
            <div><span class="lt-val mono" style="font-weight:700; font-size:1.25rem;">{{ $monthly['total'] }}</span> <span class="cell-muted" style="font-size:.8rem;">registros</span></div>
            <div><span class="lt-val mono" style="font-weight:700; font-size:1.25rem;">{{ format_money($monthly['totalAmount']) }}</span> <span class="cell-muted" style="font-size:.8rem;">cobrado</span></div>
            @foreach (\App\Models\Protest::TYPES as $key => $label)
                <div><span class="lt-val mono" style="font-weight:700; font-size:1.25rem;">{{ $monthly['byType'][$key] ?? 0 }}</span> <span class="cell-muted" style="font-size:.8rem;">{{ $label }}</span></div>
            @endforeach
        </div>
    </div>

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar" method="GET" action="{{ route('protests.index') }}" role="search">
                <select name="type" class="form-select form-select-sm" aria-label="Tipo">
                    <option value="">Tipo: todos</option>
                    @foreach (\App\Models\Protest::TYPES as $key => $label)
                        <option value="{{ $key }}" {{ $filters['type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="channel" class="form-select form-select-sm" aria-label="Vía">
                    <option value="">Vía: todas</option>
                    @foreach (\App\Models\Protest::CHANNELS as $key => $label)
                        <option value="{{ $key }}" {{ $filters['channel'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select form-select-sm" aria-label="Estado">
                    <option value="">Estado: todos</option>
                    @foreach (\App\Models\Protest::STATUS_LABELS as $key => $label)
                        <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                @if ($filters['type'] || $filters['channel'] || $filters['status'])
                    <a href="{{ route('protests.index') }}" class="btn btn-link btn-sm">Limpiar</a>
                @endif
            </form>
        </div>

        @if ($records->isEmpty())
            <x-empty-state icon="shield" title="No hay registros"
                :message="($filters['type'] || $filters['channel'] || $filters['status']) ? 'No se encontraron registros para los filtros seleccionados.' : 'Todavía no se registró ningún protesto o mora.'" />
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Vía</th>
                        <th>Deudor</th>
                        <th>Acreedor</th>
                        <th class="is-numeric">Monto</th>
                        <th>Estado</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($records as $record)
                        <tr class="{{ $record->isRegularized() ? 'row-voided' : '' }}">
                            <td class="cell-nowrap">{{ format_date($record->registered_at) }}</td>
                            <td>{{ $record->typeLabel() }}</td>
                            <td>{{ $record->channelLabel() }}</td>
                            <td class="cell-clamp">{{ $record->debtor_name }}</td>
                            <td class="cell-clamp">{{ $record->creditor_name }}</td>
                            <td class="is-numeric cell-money cell-nowrap">{{ format_money($record->amount) }}</td>
                            <td><x-status-badge :status="$record->status" /></td>
                            <td class="is-numeric">
                                <div class="row-actions">
                                    <a href="{{ route('protests.show', $record) }}" class="btn btn-ghost btn-icon" title="Ver detalle" aria-label="Ver detalle">
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
                <x-pagination-meta :paginator="$records" noun="registros" />
                {{ $records->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
