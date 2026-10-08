@extends('layouts.app')

@section('title', 'Placas')

@section('content')
    @php
        $exportParams = $isRange ? ['date_from' => $dateFrom, 'date_to' => $dateTo] : ['period' => $period];
    @endphp
    <x-page-header title="Placas" subtitle="Trámites del período o rango seleccionado, por tipo y por comprobante.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
            @can('reports.export')
                <a href="{{ route('reports.plates.export', ['format' => 'excel'] + $exportParams) }}" class="btn btn-secondary btn-sm" data-export-toast="Preparando Excel…">
                    {{ icon('file-spreadsheet', 'icon', 16) }} Excel
                </a>
                <a href="{{ route('reports.plates.export', ['format' => 'pdf'] + $exportParams) }}" class="btn btn-secondary btn-sm" data-export-toast="Preparando PDF…">
                    {{ icon('file-down', 'icon', 16) }} PDF
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface mb-4">
        <form method="GET" action="{{ route('reports.plates') }}" class="d-flex gap-3 align-items-end flex-wrap">
            <div class="field" style="margin-bottom: 0;">
                <label class="field-label" for="period">Período (un mes)</label>
                <input type="month" id="period" name="period" class="form-control" style="max-width: 180px" value="{{ $period }}">
            </div>
            <div class="cell-muted" style="font-size: var(--text-xs); padding-bottom: 10px;">— o —</div>
            <div class="field" style="margin-bottom: 0;">
                <label class="field-label" for="date_from">Desde</label>
                <input type="date" id="date_from" name="date_from" class="form-control" style="width: auto" value="{{ $dateFrom }}">
            </div>
            <div class="field" style="margin-bottom: 0;">
                <label class="field-label" for="date_to">Hasta</label>
                <input type="date" id="date_to" name="date_to" class="form-control" style="width: auto" value="{{ $dateTo }}">
            </div>
            <button type="submit" class="btn btn-secondary">Ver</button>
            @if ($isRange)
                <a href="{{ route('reports.plates', ['period' => $period]) }}" class="btn btn-link">Quitar rango</a>
            @endif
        </form>
    </div>

    <div class="kpi-grid">
        <x-kpi-card label="Trámites" icon="car" variant="navy" :value="$totalCount" />
        <x-kpi-card label="Monto cobrado" icon="wallet" variant="teal" :value="format_money($totalAmount)" />
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card-surface h-100">
                <h3 class="text-h3" style="margin-bottom: var(--space-3);">Por tipo de trámite</h3>
                <table class="data-table data-table-compact">
                    <tbody>
                    @foreach ($byProcedure as $row)
                        <tr>
                            <td class="cell-primary">{{ $row['label'] }}</td>
                            <td class="is-numeric cell-muted">{{ $row['count'] }}</td>
                            <td class="is-numeric cell-money">{{ format_money($row['total']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-surface h-100">
                <h3 class="text-h3" style="margin-bottom: var(--space-3);">Por comprobante</h3>
                <table class="data-table data-table-compact">
                    <tbody>
                    @foreach ($byReceiptType as $row)
                        <tr>
                            <td class="cell-primary">{{ $row['label'] }}</td>
                            <td class="is-numeric cell-muted">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="table-card">
        <h2 class="text-h3" style="margin-bottom: var(--space-4);">
            Trámites {{ $isRange ? 'del '.format_date($dateFrom).' al '.format_date($dateTo) : 'en '.$period }}
        </h2>
        @if ($records->isEmpty())
            <x-empty-state icon="car" title="Sin trámites" message="No se registraron trámites de placas en este período." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Placa</th>
                        <th>Solicitante</th>
                        <th>Comprobante</th>
                        <th class="is-numeric">Costo</th>
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
                            <td class="is-numeric cell-money">{{ format_money($record->amount) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
