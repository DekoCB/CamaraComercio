@extends('layouts.app')

@section('title', 'Cobrado en el mes')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <form method="GET" action="{{ route('reports.collections') }}" class="d-flex gap-2">
            <input type="month" name="period" class="form-control form-control-sm" style="max-width: 160px" value="{{ $period }}">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Ver</button>
        </form>
        @can('reports.export')
            <div class="d-flex gap-2">
                <a href="{{ route('reports.collections.export', ['format' => 'excel', 'period' => $period]) }}" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </a>
                <a href="{{ route('reports.collections.export', ['format' => 'pdf', 'period' => $period]) }}" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
            </div>
        @endcan
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Facturado del período</div>
                <div class="kpi-value">{{ format_money($totalInvoiced) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Cobrado del mes</div>
                <div class="kpi-value text-success">{{ format_money($totalCollected) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Pagos registrados</div>
                <div class="kpi-value">{{ $paymentsCount }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Asociados que pagaron</div>
                <div class="kpi-value">{{ $payingAssociatesCount }}</div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <h2 class="h6 mb-3">Pagos de {{ $period }}</h2>
        @if ($payments->isEmpty())
            <div class="empty-state">
                <p class="mb-0">No se registraron pagos en este período.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Asociado</th>
                        <th>Período factura</th>
                        <th>Monto</th>
                        <th>Registrado por</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ format_date($payment->paid_at) }}</td>
                            <td>{{ $payment->invoice->associate->name }}</td>
                            <td>{{ $payment->invoice->period }}</td>
                            <td>{{ format_money($payment->amount) }}</td>
                            <td>{{ $payment->registeredBy->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
