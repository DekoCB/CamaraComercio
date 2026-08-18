@extends('layouts.app')

@section('title', 'Deuda pendiente')

@section('content')
    <div class="d-flex justify-content-end mb-3">
        @can('reports.export')
            <div class="d-flex gap-2">
                <a href="{{ route('reports.debt.export', ['format' => 'excel']) }}" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </a>
                <a href="{{ route('reports.debt.export', ['format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
            </div>
        @endcan
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Total pendiente</div>
                <div class="kpi-value text-danger">{{ format_money($totalPending) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Asociados con deuda</div>
                <div class="kpi-value">{{ $debtorsCount }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Facturas pendientes</div>
                <div class="kpi-value">{{ $pendingInvoicesCount }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Facturas vencidas</div>
                <div class="kpi-value text-danger">{{ $overdueInvoicesCount }}</div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <h2 class="h6 mb-3">Distribución de la deuda por estado</h2>
        @if ($distribution->isEmpty())
            <div class="empty-state">
                <p class="mb-0">No hay deuda pendiente en este momento.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Facturas</th>
                        <th>Saldo</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($distribution as $row)
                        <tr>
                            <td><span class="badge badge-status-{{ $row->bucket }}">{{ $row->bucket }}</span></td>
                            <td>{{ $row->invoice_count }}</td>
                            <td>{{ format_money($row->total_balance) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
