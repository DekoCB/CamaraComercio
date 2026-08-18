@extends('layouts.app')

@section('title', 'A quién falta cobrar')

@section('content')
    <x-page-header title="A quién falta cobrar" subtitle="Asociados con saldo pendiente, para priorizar el seguimiento de cobranza.">
        <x-slot:actions>
            <a href="{{ route('portfolio.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('chevron-left', 'icon', 16) }} Ver cartera completa
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="table-card">
        <div class="table-toolbar">
            <form class="search-input" method="GET" action="{{ route('portfolio.debtors') }}">
                {{ icon('search', 'icon', 16) }}
                <input type="search" name="q" class="form-control" placeholder="Buscar por nombre o empresa" value="{{ $term }}">
            </form>
        </div>

        @if ($associates->isEmpty())
            <x-empty-state icon="check-circle-2" title="Sin deuda pendiente" message="Ningún asociado tiene deuda pendiente en este momento." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Asociado</th>
                        <th>Contacto</th>
                        <th>Correo</th>
                        <th class="is-numeric">Monto pendiente</th>
                        <th>Debe desde</th>
                        <th>Facturas pendientes</th>
                        <th>Facturas vencidas</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($associates as $associate)
                        @php $pending = (float) ($associate->total_invoiced ?? 0) - (float) ($associate->total_paid ?? 0); @endphp
                        <tr>
                            <td class="cell-primary">
                                <a href="{{ route('associates.statement', $associate) }}">{{ $associate->name }}</a>
                            </td>
                            <td class="cell-muted">{{ $associate->contact_phone ?? '-' }}</td>
                            <td class="cell-muted">{{ $associate->email ?? '-' }}</td>
                            <td class="is-numeric cell-money" style="color: var(--color-danger);">{{ format_money($pending) }}</td>
                            <td class="cell-muted">{{ $associate->oldest_pending_period ?? '-' }}</td>
                            <td class="cell-muted">{{ $associate->pending_invoices_count }}</td>
                            <td>
                                @if ($associate->overdue_invoices_count > 0)
                                    <span class="badge badge-status-VENCIDA">{{ $associate->overdue_invoices_count }}</span>
                                @else
                                    <span class="cell-muted">0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <x-pagination-meta :paginator="$associates" noun="deudores" />
                {{ $associates->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
