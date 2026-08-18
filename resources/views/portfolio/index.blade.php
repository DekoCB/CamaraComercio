@extends('layouts.app')

@section('title', 'Cartera')

@section('content')
    <x-page-header title="Seguimiento de cartera" subtitle="Total facturado, pagado y pendiente por asociado.">
        <x-slot:actions>
            @can('portfolio.view')
                <a href="{{ route('portfolio.debtors') }}" class="btn btn-secondary btn-sm">
                    {{ icon('alert-triangle', 'icon', 16) }} Ver solo quienes deben
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="table-card">
        <div class="table-toolbar">
            <form class="search-input" method="GET" action="{{ route('portfolio.index') }}">
                {{ icon('search', 'icon', 16) }}
                <input type="search" name="q" class="form-control" placeholder="Buscar por nombre o empresa" value="{{ $term }}">
            </form>
        </div>

        @if ($associates->isEmpty())
            <x-empty-state icon="trending-up" title="No hay asociados registrados" message="Registra asociados para ver su cartera aquí." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Asociado</th>
                        <th class="is-numeric">Facturado</th>
                        <th class="is-numeric">Pagado</th>
                        <th class="is-numeric">Pendiente</th>
                        <th>Facturas pendientes</th>
                        <th>Facturas vencidas</th>
                        <th class="is-numeric">Acciones</th>
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
                            <td class="cell-primary">{{ $associate->name }}</td>
                            <td class="is-numeric cell-money">{{ format_money($invoiced) }}</td>
                            <td class="is-numeric cell-money">{{ format_money($paid) }}</td>
                            <td class="is-numeric cell-money" style="{{ $pending > 0 ? 'color: var(--color-danger);' : '' }}">{{ format_money($pending) }}</td>
                            <td class="cell-muted">{{ $associate->pending_invoices_count }}</td>
                            <td>
                                @if ($associate->overdue_invoices_count > 0)
                                    <span class="badge badge-status-VENCIDA">{{ $associate->overdue_invoices_count }}</span>
                                @else
                                    <span class="cell-muted">0</span>
                                @endif
                            </td>
                            <td class="is-numeric">
                                <a href="{{ route('associates.statement', $associate) }}" class="btn btn-ghost btn-sm">
                                    {{ icon('file-text', 'icon', 15) }} Estado de cuenta
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <x-pagination-meta :paginator="$associates" noun="asociados" />
                {{ $associates->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
