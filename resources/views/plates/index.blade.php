@extends('layouts.app')

@section('title', 'Placas')

@section('content')
    <x-page-header title="Emisión de placas" subtitle="Registro de trámites de placas vehiculares: nueva placa, duplicado, tercera placa, boleta informativa y otros trámites.">
        <x-slot:actions>
            <a href="{{ route('plates.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Nuevo trámite de placa">
                {{ icon('plus', 'icon', 16) }} Nuevo trámite
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface mb-3">
        <h3 class="form-section-title" style="padding: 0;">Este mes</h3>
        <div class="d-flex gap-4 flex-wrap" style="margin-top: 6px;">
            <div><span class="lt-val mono" style="font-weight:700; font-size:1.25rem;">{{ $monthly['total'] }}</span> <span class="cell-muted" style="font-size:.8rem;">trámites</span></div>
            <div><span class="lt-val mono" style="font-weight:700; font-size:1.25rem;">{{ format_money($monthly['totalAmount']) }}</span> <span class="cell-muted" style="font-size:.8rem;">cobrado</span></div>
            @foreach (\App\Models\PlateIssuance::PROCEDURE_TYPES as $key => $label)
                <div><span class="lt-val mono" style="font-weight:700; font-size:1.25rem;">{{ $monthly['byProcedure'][$key] ?? 0 }}</span> <span class="cell-muted" style="font-size:.8rem;">{{ $label }}</span></div>
            @endforeach
        </div>
    </div>

    <div class="card-surface mb-3" style="max-width: 640px;">
        <h3 class="form-section-title" style="padding: 0;">Tarifas por tipo de trámite</h3>
        <p class="cell-muted" style="font-size: var(--text-xs); margin-top: -4px;">Sugerida al registrar un trámite — siempre se puede ajustar el costo caso por caso.</p>
        <form method="POST" action="{{ route('plates.rates.update') }}" novalidate>
            @csrf
            @method('PUT')
            <div class="row g-3">
                @foreach (\App\Models\PlateIssuance::PROCEDURE_TYPES as $key => $label)
                    <div class="col-md-6">
                        <div class="field">
                            <label class="field-label" for="rate_{{ $key }}">{{ $label }}</label>
                            <div class="input-money">
                                <span class="currency-prefix">S/</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="rate_{{ $key }}" name="rates[{{ $key }}]" value="{{ old('rates.'.$key, $rates[$key] ?? '') }}">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">{{ icon('check', 'icon', 15) }} Guardar tarifas</button>
        </form>
    </div>

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar" method="GET" action="{{ route('plates.index') }}" role="search">
                <select name="procedure_type" class="form-select form-select-sm" aria-label="Tipo de trámite">
                    <option value="">Tipo: todos</option>
                    @foreach (\App\Models\PlateIssuance::PROCEDURE_TYPES as $key => $label)
                        <option value="{{ $key }}" {{ $filters['procedure_type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="receipt_type" class="form-select form-select-sm" aria-label="Comprobante">
                    <option value="">Comprobante: todos</option>
                    @foreach (\App\Models\PlateIssuance::RECEIPT_TYPES as $key => $label)
                        <option value="{{ $key }}" {{ $filters['receipt_type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                @if ($filters['procedure_type'] || $filters['receipt_type'])
                    <a href="{{ route('plates.index') }}" class="btn btn-link btn-sm">Limpiar</a>
                @endif
            </form>
        </div>

        @if ($records->isEmpty())
            <x-empty-state icon="car" title="No hay trámites"
                :message="($filters['procedure_type'] || $filters['receipt_type']) ? 'No se encontraron trámites para los filtros seleccionados.' : 'Todavía no se registró ningún trámite de placa.'" />
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Placa</th>
                        <th>Solicitante</th>
                        <th>Comprobante</th>
                        <th class="is-numeric">Costo</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td class="cell-nowrap">{{ format_date($record->issued_at) }}</td>
                            <td>{{ $record->procedureLabel() }}</td>
                            <td class="cell-nowrap">{{ $record->plate_number ?? '-' }}</td>
                            <td class="cell-clamp">{{ $record->requesterLabel() }}</td>
                            <td>{{ $record->receiptLabel() }}</td>
                            <td class="is-numeric cell-money cell-nowrap">{{ format_money($record->amount) }}</td>
                            <td class="is-numeric">
                                <div class="row-actions">
                                    <a href="{{ route('plates.show', $record) }}" class="btn btn-ghost btn-icon" title="Ver detalle" aria-label="Ver detalle">
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
                <x-pagination-meta :paginator="$records" noun="trámites" />
                {{ $records->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
