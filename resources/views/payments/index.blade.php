@extends('layouts.app')

@section('title', 'Pagos')

@section('content')
    <x-page-header title="Registro de pagos" subtitle="Historial de todos los pagos registrados en el sistema." />

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar" method="GET" action="{{ route('payments.index') }}">
                <select name="associate_id" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                    <option value="">Todos los asociados</option>
                    @foreach ($associates as $associate)
                        <option value="{{ $associate->id }}" {{ (string) ($filters['associate_id'] ?? '') === (string) $associate->id ? 'selected' : '' }}>
                            {{ $associate->name }}
                        </option>
                    @endforeach
                </select>
                <input type="date" name="date_from" class="form-control form-control-sm" style="width: auto" value="{{ $filters['date_from'] ?? '' }}">
                <input type="date" name="date_to" class="form-control form-control-sm" style="width: auto" value="{{ $filters['date_to'] ?? '' }}">
                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                @if (array_filter($filters))
                    <a href="{{ route('payments.index') }}" class="btn btn-link btn-sm">Limpiar</a>
                @endif
            </form>
        </div>

        @if ($payments->isEmpty())
            <x-empty-state icon="wallet" title="No hay pagos registrados" message="No se encontraron pagos para los filtros seleccionados." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Asociado</th>
                        <th>Período</th>
                        <th class="is-numeric">Monto</th>
                        <th>Registrado por</th>
                        <th class="is-numeric">Acciones</th>
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
                            <td class="is-numeric">
                                <a href="{{ route('invoices.show', $payment->invoice) }}" class="btn btn-ghost btn-sm">
                                    {{ icon('eye', 'icon', 15) }} Ver factura
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <x-pagination-meta :paginator="$payments" noun="pagos" />
                {{ $payments->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
