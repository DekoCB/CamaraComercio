@php
    $filterLabels = [
        'q' => 'Búsqueda',
        'status' => 'Estado',
        'year' => 'Año',
        'month' => 'Mes',
        'sectorista' => 'Sectorista',
        'category' => 'Categoría',
        'associate_id' => 'Asociado',
        'period' => 'Período',
    ];
    $pretty = fn ($key, $value) => match ($key) {
        'status' => $statusFilters[$value] ?? $value,
        'month' => $months[(int) $value] ?? $value,
        'associate_id' => $filteredAssociate?->name ?? $value,
        'period' => format_period($value),
        default => $value,
    };
    $activeFilters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
@endphp

@if ($activeFilters)
    <div class="filter-chips" aria-label="Filtros activos">
        <span class="filter-chips-label">{{ $invoices->total() }} factura{{ $invoices->total() === 1 ? '' : 's' }} con:</span>
        @foreach ($activeFilters as $key => $value)
            <a href="{{ route('invoices.index', array_diff_key($activeFilters, [$key => true])) }}" class="filter-chip js-live-link" title="Quitar este filtro">
                <span class="filter-chip-key">{{ $filterLabels[$key] ?? $key }}</span>
                {{ $pretty($key, $value) }}
                {{ icon('x', 'icon', 12) }}
            </a>
        @endforeach
    </div>
@endif

@if ($invoices->isEmpty())
    <x-empty-state icon="file-text" title="No hay facturas"
        :message="$activeFilters ? 'No se encontraron facturas para los filtros seleccionados.' : 'Todavía no se ha generado ninguna factura.'" />
@else
    <div class="table-wrap">
        <table class="data-table data-table-compact">
            <thead>
            <tr>
                <th>Asociado</th>
                <th>Período</th>
                <th>Comprobante</th>
                <th class="is-numeric">Monto</th>
                <th class="is-numeric">Pagado</th>
                <th class="is-numeric">Saldo</th>
                <th>Vence</th>
                <th>Estado</th>
                <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($invoices as $invoice)
                <tr>
                    <td class="cell-primary cell-clamp">
                        <a href="{{ route('associates.show', $invoice->associate) }}" class="link-plain">{{ $invoice->associate->name }}</a>
                        @if ($invoice->associate->ruc)
                            <div class="cell-muted" style="font-size: var(--text-xs); font-weight: 400;">RUC {{ $invoice->associate->ruc }}</div>
                        @endif
                    </td>
                    <td class="cell-nowrap">
                        {{ format_period($invoice->period) }}
                        <div class="cell-muted" style="font-size: var(--text-xs);">{{ $invoice->period }}</div>
                    </td>
                    <td class="cell-muted cell-nowrap">{{ $invoice->receipt_number ?? '-' }}</td>
                    <td class="is-numeric cell-money cell-nowrap">{{ format_money($invoice->amount) }}</td>
                    <td class="is-numeric cell-money cell-nowrap">{{ format_money($invoice->paid_total) }}</td>
                    <td class="is-numeric cell-money cell-nowrap {{ $invoice->balance() > 0 ? 'text-danger' : '' }}">{{ format_money($invoice->balance()) }}</td>
                    <td class="cell-muted cell-nowrap">{{ format_date($invoice->due_date) }}</td>
                    <td><x-status-badge :status="$invoice->effectiveStatus()" /></td>
                    <td class="is-numeric">
                        <div class="row-actions">
                            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost btn-icon" title="Ver detalle" aria-label="Ver detalle">
                                {{ icon('eye', 'icon', 16) }}
                            </a>
                            @can('payments.register')
                                @if ($invoice->balance() > 0)
                                    <a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-ghost btn-icon js-modal-link" title="Registrar pago" aria-label="Registrar pago"
                                       data-modal-title="Registrar pago — {{ $invoice->associate->name }} · {{ format_period($invoice->period) }}">
                                        {{ icon('wallet', 'icon', 16) }}
                                    </a>
                                @endif
                            @endcan
                        </div>
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
