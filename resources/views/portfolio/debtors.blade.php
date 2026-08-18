@extends('layouts.app')

@section('title', 'A quién falta cobrar')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <form class="d-flex gap-2" method="GET" action="{{ route('portfolio.debtors') }}">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Buscar por nombre o empresa"
                   value="{{ $term }}" style="min-width: 240px">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route('portfolio.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Ver cartera completa
        </a>
    </div>

    <div class="table-card">
        @if ($associates->isEmpty())
            <div class="empty-state">
                <i class="bi bi-emoji-smile fs-1"></i>
                <p class="mb-0 mt-2">Ningún asociado tiene deuda pendiente en este momento.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Asociado</th>
                        <th>Contacto</th>
                        <th>Correo</th>
                        <th>Monto pendiente</th>
                        <th>Debe desde</th>
                        <th>Facturas pendientes</th>
                        <th>Facturas vencidas</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($associates as $associate)
                        @php $pending = (float) ($associate->total_invoiced ?? 0) - (float) ($associate->total_paid ?? 0); @endphp
                        <tr>
                            <td>
                                <a href="{{ route('associates.statement', $associate) }}">{{ $associate->name }}</a>
                            </td>
                            <td>{{ $associate->contact_phone ?? '-' }}</td>
                            <td>{{ $associate->email ?? '-' }}</td>
                            <td class="text-danger fw-semibold">{{ format_money($pending) }}</td>
                            <td>{{ $associate->oldest_pending_period ?? '-' }}</td>
                            <td>{{ $associate->pending_invoices_count }}</td>
                            <td>
                                @if ($associate->overdue_invoices_count > 0)
                                    <span class="badge badge-status-VENCIDA">{{ $associate->overdue_invoices_count }}</span>
                                @else
                                    0
                                @endif
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
