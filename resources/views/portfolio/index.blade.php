@extends('layouts.app')

@section('title', 'Cartera')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted small mb-0">Total facturado, pagado y pendiente por asociado.</p>
        @can('portfolio.view')
            <a href="{{ route('portfolio.debtors') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-exclamation-circle"></i> Ver solo quienes deben
            </a>
        @endcan
    </div>

    <div class="table-card">
        @if ($associates->isEmpty())
            <div class="empty-state">
                <i class="bi bi-graph-up fs-1"></i>
                <p class="mb-0 mt-2">No hay asociados registrados todavía.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Asociado</th>
                        <th>Facturado</th>
                        <th>Pagado</th>
                        <th>Pendiente</th>
                        <th>Facturas pendientes</th>
                        <th>Facturas vencidas</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($associates as $associate)
                        @php
                            $invoiced = (float) ($associate->total_invoiced ?? 0);
                            $paid = (float) ($associate->total_paid ?? 0);
                            $pending = $invoiced - $paid;
                        @endphp
                        <tr>
                            <td>{{ $associate->name }}</td>
                            <td>{{ format_money($invoiced) }}</td>
                            <td>{{ format_money($paid) }}</td>
                            <td class="{{ $pending > 0 ? 'text-danger fw-semibold' : '' }}">{{ format_money($pending) }}</td>
                            <td>{{ $associate->pending_invoices_count }}</td>
                            <td>
                                @if ($associate->overdue_invoices_count > 0)
                                    <span class="badge badge-status-VENCIDA">{{ $associate->overdue_invoices_count }}</span>
                                @else
                                    0
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('associates.statement', $associate) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-file-earmark-text"></i> Estado de cuenta
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $associates->onEachSide(1)->links() }}</div>
        @endif
    </div>
@endsection
