@extends('layouts.app')

@section('title', 'Cobrado en el mes')

@section('content')
    <x-page-header title="Lo cobrado en el mes" subtitle="Facturado (devengo) vs. cobrado (caja) para el período seleccionado.">
        <x-slot:actions>
            @can('reports.export')
                <a href="{{ route('reports.collections.export', ['format' => 'excel', 'period' => $period]) }}" class="btn btn-secondary btn-sm" data-export-toast="Preparando Excel…">
                    {{ icon('file-spreadsheet', 'icon', 16) }} Excel
                </a>
                <a href="{{ route('reports.collections.export', ['format' => 'pdf', 'period' => $period]) }}" class="btn btn-secondary btn-sm" data-export-toast="Preparando PDF…">
                    {{ icon('file-down', 'icon', 16) }} PDF
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface mb-4">
        <form method="GET" action="{{ route('reports.collections') }}" class="d-flex gap-2 align-items-end">
            <div class="field" style="margin-bottom: 0;">
                <label class="field-label" for="period">Período</label>
                <input type="month" id="period" name="period" class="form-control" style="max-width: 180px" value="{{ $period }}">
            </div>
            <button type="submit" class="btn btn-secondary">Ver</button>
        </form>
    </div>

    <div class="kpi-grid">
        <x-kpi-card label="Facturado del período" icon="file-text" variant="blue" :value="format_money($totalInvoiced)" />
        <x-kpi-card label="Cobrado del mes" icon="wallet" variant="teal" :value="format_money($totalCollected)" />
        <x-kpi-card label="Pagos registrados" icon="receipt" variant="navy" :value="$paymentsCount" />
        <x-kpi-card label="Asociados que pagaron" icon="users" variant="navy" :value="$payingAssociatesCount" />
    </div>

    <div class="table-card">
        <h2 class="text-h3" style="margin-bottom: var(--space-4);">Pagos de {{ $period }}</h2>
        @if ($payments->isEmpty())
            <x-empty-state icon="wallet" title="Sin pagos" message="No se registraron pagos en este período." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Asociado</th>
                        <th>Período factura</th>
                        <th class="is-numeric">Monto</th>
                        <th>Registrado por</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="cell-muted">{{ format_date($payment->paid_at) }}</td>
                            <td class="cell-primary">{{ $payment->invoice->associate->name }}</td>
                            <td class="cell-muted">{{ $payment->invoice->period }}</td>
                            <td class="is-numeric cell-money">{{ format_money($payment->amount) }}</td>
                            <td class="cell-muted">{{ $payment->registeredBy->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
