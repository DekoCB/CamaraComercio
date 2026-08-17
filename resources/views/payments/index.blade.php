@extends('layouts.app')

@section('title', 'Pagos')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <form class="d-flex flex-wrap gap-2" method="GET" action="{{ route('payments.index') }}">
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
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
            @if (array_filter($filters))
                <a href="{{ route('payments.index') }}" class="btn btn-sm btn-link">Limpiar</a>
            @endif
        </form>
    </div>

    <div class="table-card">
        @if ($payments->isEmpty())
            <div class="empty-state">
                <i class="bi bi-cash-coin fs-1"></i>
                <p class="mb-0 mt-2">No hay pagos registrados para los filtros seleccionados.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Asociado</th>
                        <th>Período</th>
                        <th>Monto</th>
                        <th>Registrado por</th>
                        <th class="text-end">Acciones</th>
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
                            <td class="text-end">
                                <a href="{{ route('invoices.show', $payment->invoice) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i> Ver factura
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $payments->onEachSide(1)->links() }}</div>
        @endif
    </div>
@endsection
