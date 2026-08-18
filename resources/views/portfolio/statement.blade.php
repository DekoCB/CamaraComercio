@extends('layouts.app')

@section('title', 'Estado de cuenta — '.$associate->name)

@section('content')
    <div class="table-card mb-3">
        <h2 class="h5 mb-1">{{ $associate->name }}</h2>
        <div class="text-muted small">{{ $associate->company }}</div>
        <div class="text-muted small">{{ $associate->contact_phone ?? '-' }} · {{ $associate->email ?? '-' }}</div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-4">
            <div class="kpi-card">
                <div class="kpi-label">Total facturado</div>
                <div class="kpi-value">{{ format_money($totalInvoiced) }}</div>
            </div>
        </div>
        <div class="col-4">
            <div class="kpi-card">
                <div class="kpi-label">Total pagado</div>
                <div class="kpi-value text-success">{{ format_money($totalPaid) }}</div>
            </div>
        </div>
        <div class="col-4">
            <div class="kpi-card">
                <div class="kpi-label">Saldo pendiente</div>
                <div class="kpi-value {{ $totalPending > 0 ? 'text-danger' : '' }}">{{ format_money($totalPending) }}</div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <h2 class="h6 mb-3">Historial de facturas</h2>
        @if ($invoices->isEmpty())
            <div class="empty-state">
                <p class="mb-0">Este asociado todavía no tiene facturas.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Período</th>
                        <th>Monto</th>
                        <th>Pagado</th>
                        <th>Saldo</th>
                        <th>Vence</th>
                        <th>Estado</th>
                        <th>Pagos</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->period }}</td>
                            <td>{{ format_money($invoice->amount) }}</td>
                            <td>{{ format_money($invoice->paid_total) }}</td>
                            <td>{{ format_money($invoice->balance()) }}</td>
                            <td>{{ format_date($invoice->due_date) }}</td>
                            <td>@include('invoices._status_badge', ['invoice' => $invoice])</td>
                            <td>{{ $invoice->payments->count() }}</td>
                            <td class="text-end">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
