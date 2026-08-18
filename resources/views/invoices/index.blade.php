@extends('layouts.app')

@section('title', 'Facturación')

@section('content')
    <x-page-header title="Facturación" subtitle="Consulta y genera la facturación mensual de los asociados.">
        <x-slot:actions>
            @can('billing.generate')
                <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Generar facturación del mes">
                    {{ icon('plus', 'icon', 16) }} Generar facturación del mes
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="table-card">
        <div class="table-toolbar">
            <form class="filter-bar" method="GET" action="{{ route('invoices.index') }}">
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
                <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Filtrar</button>
                @if (array_filter($filters))
                    <a href="{{ route('invoices.index') }}" class="btn btn-link btn-sm">Limpiar</a>
                @endif
            </form>
        </div>

        @if ($invoices->isEmpty())
            <x-empty-state icon="file-text" title="No hay facturas" message="No se encontraron facturas para los filtros seleccionados." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Asociado</th>
                        <th>Período</th>
                        <th class="is-numeric">Monto</th>
                        <th class="is-numeric">Pagado</th>
                        <th class="is-numeric">Saldo</th>
                        <th>Vence</th>
                        <th>Estado</th>
                        <th class="is-numeric">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="cell-primary">{{ $invoice->associate->name }}</td>
                            <td class="cell-muted">{{ $invoice->period }}</td>
                            <td class="is-numeric cell-money">{{ format_money($invoice->amount) }}</td>
                            <td class="is-numeric cell-money">{{ format_money($invoice->paid_total) }}</td>
                            <td class="is-numeric cell-money">{{ format_money($invoice->balance()) }}</td>
                            <td class="cell-muted">{{ format_date($invoice->due_date) }}</td>
                            <td><x-status-badge :status="$invoice->effectiveStatus()" /></td>
                            <td class="is-numeric">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">
                                    {{ icon('eye', 'icon', 15) }} Ver
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <x-pagination-meta :paginator="$invoices" noun="facturas" />
                {{ $invoices->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
