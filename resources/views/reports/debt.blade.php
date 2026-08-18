@extends('layouts.app')

@section('title', 'Deuda pendiente')

@section('content')
    <x-page-header title="Deuda pendiente" subtitle="Fotografía de lo que se adeuda hoy, por estado.">
        <x-slot:actions>
            @can('reports.export')
                <a href="{{ route('reports.debt.export', ['format' => 'excel']) }}" class="btn btn-secondary btn-sm" data-export-toast="Preparando Excel…">
                    {{ icon('file-spreadsheet', 'icon', 16) }} Excel
                </a>
                <a href="{{ route('reports.debt.export', ['format' => 'pdf']) }}" class="btn btn-secondary btn-sm" data-export-toast="Preparando PDF…">
                    {{ icon('file-down', 'icon', 16) }} PDF
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="kpi-grid">
        <x-kpi-card label="Total pendiente" icon="alert-triangle" variant="danger" :value="format_money($totalPending)" :critical="$totalPending > 0" />
        <x-kpi-card label="Asociados con deuda" icon="users" variant="navy" :value="$debtorsCount" />
        <x-kpi-card label="Facturas pendientes" icon="file-text" variant="blue" :value="$pendingInvoicesCount" />
        <x-kpi-card label="Facturas vencidas" icon="clock" variant="danger" :value="$overdueInvoicesCount" />
    </div>

    <div class="table-card">
        <h2 class="text-h3" style="margin-bottom: var(--space-4);">Distribución de la deuda por estado</h2>
        @if ($distribution->isEmpty())
            <x-empty-state icon="check-circle-2" title="Sin deuda pendiente" message="No hay deuda pendiente en este momento." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Estado</th>
                        <th class="is-numeric">Facturas</th>
                        <th class="is-numeric">Saldo</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($distribution as $row)
                        <tr>
                            <td><x-status-badge :status="$row->bucket" /></td>
                            <td class="is-numeric cell-muted">{{ $row->invoice_count }}</td>
                            <td class="is-numeric cell-money">{{ format_money($row->total_balance) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
