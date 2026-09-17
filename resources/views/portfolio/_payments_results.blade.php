@php
    $activeFilters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
    $filterLabels = ['q' => 'Búsqueda', 'year' => 'Año', 'month' => 'Mes', 'date_from' => 'Desde', 'date_to' => 'Hasta', 'method' => 'Método', 'state' => 'Estado', 'sectorista' => 'Sectorista', 'category' => 'Categoría'];
    $pretty = fn ($key, $value) => match ($key) {
        'month' => $months[(int) $value] ?? $value,
        'method' => $methodLabels[$value] ?? $value,
        'state' => $value === 'validos' ? 'Válidos' : 'Anulados',
        'date_from', 'date_to' => format_date($value),
        default => $value,
    };
    $topMethod = $summary['by_method'][0] ?? null;
@endphp

<div class="kpi-grid kpi-grid-compact">
    <x-kpi-card label="Cobrado" icon="wallet" variant="teal" :value="format_money($summary['total'])"
                :footnote="number_format($summary['count']).' pago(s) válido(s)'" />
    <x-kpi-card label="Pago promedio" icon="receipt" variant="blue" :value="format_money($summary['count'] > 0 ? $summary['total'] / $summary['count'] : 0)"
                footnote="Por movimiento válido" />
    <x-kpi-card label="Método más usado" icon="credit-card" variant="navy" :value="$topMethod ? $topMethod['label'] : '—'"
                :footnote="$topMethod ? number_format($topMethod['count']).' pago(s) · '.format_money($topMethod['total']) : 'Sin pagos en la selección'" />
    <x-kpi-card label="Anulados" icon="x-circle" variant="danger" :value="number_format($summary['voided_count'])"
                :critical="$summary['voided_count'] > 0" footnote="Se muestran tachados, no suman"
                :href="route('portfolio.payments', ['state' => 'anulados'] + $activeFilters)" :active="($filters['state'] ?? null) === 'anulados'" hint="Ver solo pagos anulados" />
</div>

@if (count($summary['by_method']) > 1)
    <div class="method-breakdown" aria-label="Cobrado por método de pago">
        @foreach ($summary['by_method'] as $row)
            <a href="{{ route('portfolio.payments', ['method' => $row['method'] ?: 'sin_metodo'] + $activeFilters) }}" class="method-chip js-live-link" title="Filtrar por {{ $row['label'] }}">
                <span class="method-chip-label">{{ $row['label'] }}</span>
                <strong>{{ format_money($row['total']) }}</strong>
                <span class="cell-muted">{{ $summary['total'] > 0 ? round($row['total'] / $summary['total'] * 100) : 0 }} %</span>
            </a>
        @endforeach
    </div>
@endif

@include('portfolio._filter_chips', ['activeFilters' => $activeFilters, 'filterLabels' => $filterLabels, 'pretty' => $pretty, 'route' => 'portfolio.payments', 'count' => $payments->total(), 'noun' => 'movimiento(s)'])

@if ($payments->isEmpty())
    <x-empty-state icon="wallet" title="Sin movimientos" :message="$activeFilters ? 'No hay pagos que coincidan con los filtros seleccionados.' : 'Todavía no se ha registrado ningún pago.'" />
@else
    <div class="table-wrap">
        <table class="data-table data-table-compact">
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Asociado</th>
                <th>Cuota</th>
                <th>Comprobante</th>
                <th class="is-numeric">Monto</th>
                <th>Método</th>
                <th>Registrado por</th>
                <th>Notas</th>
                <th>Estado</th>
                <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($payments as $payment)
                <tr class="{{ $payment->isVoided() ? 'row-voided' : '' }}">
                    <td class="cell-nowrap">
                        {{ format_date($payment->paid_at) }}
                        <div class="cell-muted" style="font-size: var(--text-xs);">{{ $payment->paid_at->format('H:i') }}</div>
                    </td>
                    <td class="cell-primary cell-clamp">
                        <a href="{{ route('associates.statement', $payment->invoice->associate) }}" class="link-plain">{{ $payment->invoice->associate->name }}</a>
                        @if ($payment->invoice->associate->ruc)
                            <div class="cell-muted" style="font-size: var(--text-xs); font-weight: 400;">RUC {{ $payment->invoice->associate->ruc }}</div>
                        @endif
                    </td>
                    <td class="cell-nowrap">{{ format_period($payment->invoice->period) }}</td>
                    <td class="cell-muted cell-nowrap">{{ $payment->invoice->receipt_number ?? '-' }}</td>
                    <td class="is-numeric cell-money cell-nowrap">{{ format_money($payment->amount) }}</td>
                    <td class="cell-nowrap">
                        <span class="badge badge-info">{{ $payment->methodLabel() }}</span>
                        @if ($payment->operation_number)
                            <div class="cell-muted" style="font-size: var(--text-xs);">N° {{ $payment->operation_number }}</div>
                        @endif
                    </td>
                    <td class="cell-muted cell-nowrap">{{ $payment->registeredBy->name ?? '-' }}</td>
                    <td class="cell-muted cell-email" title="{{ $payment->isVoided() ? $payment->void_reason : $payment->notes }}">{{ $payment->isVoided() ? ($payment->void_reason ?: '-') : ($payment->notes ?? '-') }}</td>
                    <td>
                        @if ($payment->isVoided())
                            <span class="badge badge-neutral">Anulado</span>
                        @else
                            <span class="badge badge-success">Válido</span>
                        @endif
                    </td>
                    <td class="is-numeric">
                        <div class="row-actions">
                            <a href="{{ route('invoices.show', $payment->invoice) }}" class="btn btn-ghost btn-icon" title="Ver factura" aria-label="Ver factura">{{ icon('eye', 'icon', 16) }}</a>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <x-pagination-meta :paginator="$payments" noun="movimientos" />
        {{ $payments->onEachSide(1)->links() }}
    </div>
@endif
