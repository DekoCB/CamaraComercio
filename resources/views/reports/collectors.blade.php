@extends('layouts.app')

@section('title', 'Productividad por cobrador')

@section('content')
    @php
        $exportParams = $isRange ? ['date_from' => $dateFrom, 'date_to' => $dateTo] : ['period' => $period];
    @endphp
    <x-page-header title="Productividad por cobrador" subtitle="Cuánto cobró cada usuario en el período o rango seleccionado.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
            @can('reports.export')
                <a href="{{ route('reports.collectors.export', ['format' => 'excel'] + $exportParams) }}" class="btn btn-secondary btn-sm" data-export-toast="Preparando Excel…">
                    {{ icon('file-spreadsheet', 'icon', 16) }} Excel
                </a>
                <a href="{{ route('reports.collectors.export', ['format' => 'pdf'] + $exportParams) }}" class="btn btn-secondary btn-sm" data-export-toast="Preparando PDF…">
                    {{ icon('file-down', 'icon', 16) }} PDF
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface mb-4">
        <form method="GET" action="{{ route('reports.collectors') }}" class="d-flex gap-3 align-items-end flex-wrap">
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
                <a href="{{ route('reports.collectors', ['period' => $period]) }}" class="btn btn-link">Quitar rango</a>
            @endif
        </form>
    </div>

    <div class="kpi-grid">
        <x-kpi-card label="Total cobrado" icon="wallet" variant="teal" :value="format_money($totalCollected)" />
        <x-kpi-card label="Pagos registrados" icon="receipt" variant="navy" :value="$paymentsCount" />
        <x-kpi-card label="Cobradores con movimientos" icon="users" variant="blue" :value="$collectorsCount" />
    </div>

    <div class="table-card">
        <h2 class="text-h3" style="margin-bottom: var(--space-4);">
            Cobrado por usuario {{ $isRange ? 'del '.format_date($dateFrom).' al '.format_date($dateTo) : 'en '.$period }}
        </h2>
        @if (empty($byCollector))
            <x-empty-state icon="users" title="Sin pagos" message="No se registraron pagos en este período." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Cobrador</th>
                        <th class="is-numeric">Pagos</th>
                        <th class="is-numeric">Total cobrado</th>
                        <th class="is-numeric">Promedio por pago</th>
                        <th class="is-numeric">% del total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($byCollector as $row)
                        <tr>
                            <td class="cell-primary">{{ $row['name'] }}</td>
                            <td class="is-numeric cell-muted">{{ $row['count'] }}</td>
                            <td class="is-numeric cell-money">{{ format_money($row['total']) }}</td>
                            <td class="is-numeric cell-money">{{ format_money($row['average']) }}</td>
                            <td class="is-numeric cell-muted">{{ $row['share'] }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
