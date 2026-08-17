@extends('layouts.app')

@section('title', 'Facturación')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <form class="d-flex flex-wrap gap-2" method="GET" action="{{ route('invoices.index') }}">
            <select name="associate_id" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                <option value="">Todos los asociados</option>
                @foreach ($associates as $associate)
                    <option value="{{ $associate->id }}" {{ (string) ($filters['associate_id'] ?? '') === (string) $associate->id ? 'selected' : '' }}>
                        {{ $associate->name }}
                    </option>
                @endforeach
            </select>
            <input type="text" name="period" class="form-control form-control-sm" style="width: 120px" placeholder="AAAA-MM"
                   value="{{ $filters['period'] ?? '' }}">
            <select name="status" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                @foreach (['PENDIENTE', 'PARCIAL', 'PAGADA', 'VENCIDA'] as $status)
                    <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
            @if (array_filter($filters))
                <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-link">Limpiar</a>
            @endif
        </form>
        @can('billing.generate')
            <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Generar facturación del mes
            </a>
        @endcan
    </div>

    <div class="table-card">
        @if ($invoices->isEmpty())
            <div class="empty-state">
                <i class="bi bi-receipt fs-1"></i>
                <p class="mb-0 mt-2">No hay facturas para los filtros seleccionados.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Asociado</th>
                        <th>Período</th>
                        <th>Monto</th>
                        <th>Pagado</th>
                        <th>Saldo</th>
                        <th>Vence</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->associate->name }}</td>
                            <td>{{ $invoice->period }}</td>
                            <td>{{ format_money($invoice->amount) }}</td>
                            <td>{{ format_money($invoice->paid_total) }}</td>
                            <td>{{ format_money($invoice->balance()) }}</td>
                            <td>{{ format_date($invoice->due_date) }}</td>
                            <td>@include('invoices._status_badge', ['invoice' => $invoice])</td>
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
            <div class="mt-3">{{ $invoices->onEachSide(1)->links() }}</div>
        @endif
    </div>
@endsection
