@php
    $activeFilters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
    $filterLabels = ['q' => 'Búsqueda', 'status' => 'Situación', 'sectorista' => 'Sectorista', 'category' => 'Categoría'];
    $pretty = fn ($key, $value) => $key === 'status' ? ($statusFilters[$value] ?? $value) : $value;
@endphp

<div class="kpi-grid kpi-grid-compact">
    <x-kpi-card label="Facturado" icon="receipt" variant="navy" :value="format_money($summary['billed'])"
                :footnote="number_format($summary['associates']).' asociado(s) en la selección'" />
    <x-kpi-card label="Cobrado" icon="check-circle-2" variant="teal" :value="format_money($summary['paid'])"
                :footnote="number_format($summary['collection_rate'], 1).' % de lo facturado'" />
    <x-kpi-card label="Pendiente de cobro" icon="clock" variant="warning" :value="format_money($summary['balance'])"
                :footnote="number_format($summary['debtors']).' asociado(s) con deuda'"
                :href="route('portfolio.index', ['status' => 'con_deuda'] + $activeFilters)" :active="($filters['status'] ?? null) === 'con_deuda'" hint="Ver solo asociados con deuda" />
    <x-kpi-card label="Vencido" icon="alert-triangle" variant="danger" :value="format_money($summary['overdue_balance'])"
                :critical="$summary['overdue_balance'] > 0" footnote="Saldo con fecha de vencimiento pasada"
                :href="route('portfolio.index', ['status' => 'con_vencidas'] + $activeFilters)" :active="($filters['status'] ?? null) === 'con_vencidas'" hint="Ver solo asociados con facturas vencidas" />
</div>

@include('portfolio._filter_chips', ['activeFilters' => $activeFilters, 'filterLabels' => $filterLabels, 'pretty' => $pretty, 'route' => 'portfolio.index', 'count' => $associates->total(), 'noun' => 'asociado(s)'])

@if ($associates->isEmpty())
    <x-empty-state icon="trending-up" title="Sin resultados"
        :message="$activeFilters ? 'Ningún asociado coincide con los filtros seleccionados.' : 'Registra asociados para ver su cartera aquí.'" />
@else
    <div class="table-wrap">
        <table class="data-table data-table-compact">
            <thead>
            <tr>
                <th>Asociado</th>
                <th>Sectorista</th>
                <th class="is-numeric">Facturado</th>
                <th class="is-numeric">Pagado</th>
                <th class="is-numeric">Pendiente</th>
                <th style="min-width: 140px;">Avance de cobro</th>
                <th class="is-numeric">Pendientes</th>
                <th class="is-numeric">Vencidas</th>
                <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($associates as $associate)
                @php
                    $invoiced = (float) ($associate->total_invoiced ?? 0);
                    $paid = (float) ($associate->total_paid ?? 0);
                    $pending = $invoiced - $paid;
                    $percent = $invoiced > 0 ? min(100, round($paid / $invoiced * 100)) : null;
                @endphp
                <tr>
                    <td class="cell-primary cell-clamp">
                        <a href="{{ route('associates.statement', $associate) }}" class="link-plain">{{ $associate->name }}</a>
                        @if ($associate->ruc)
                            <div class="cell-muted" style="font-size: var(--text-xs); font-weight: 400;">RUC {{ $associate->ruc }}</div>
                        @endif
                    </td>
                    <td class="cell-muted cell-nowrap">{{ $associate->sectorista ?? '-' }}{{ $associate->category ? ' · '.$associate->category : '' }}</td>
                    <td class="is-numeric cell-money cell-nowrap">{{ format_money($invoiced) }}</td>
                    <td class="is-numeric cell-money cell-nowrap">{{ format_money($paid) }}</td>
                    <td class="is-numeric cell-money cell-nowrap" style="{{ $pending > 0 ? 'color: var(--color-danger);' : 'color: var(--color-text-secondary); font-weight: 400;' }}">{{ format_money($pending) }}</td>
                    <td>
                        @if ($percent === null)
                            <span class="cell-muted">Sin facturas</span>
                        @else
                            <div class="mini-progress" title="{{ $percent }} % cobrado">
                                <div class="mini-progress-bar {{ $percent >= 100 ? 'is-complete' : ($associate->overdue_invoices_count > 0 ? 'is-danger' : '') }}" style="width: {{ $percent }}%"></div>
                            </div>
                            <div class="cell-muted" style="font-size: var(--text-xs);">{{ $percent }} %</div>
                        @endif
                    </td>
                    <td class="is-numeric cell-muted">{{ $associate->pending_invoices_count }}</td>
                    <td class="is-numeric">
                        @if ($associate->overdue_invoices_count > 0)
                            <span class="badge badge-status-VENCIDA">{{ $associate->overdue_invoices_count }}</span>
                        @else
                            <span class="cell-muted">0</span>
                        @endif
                    </td>
                    <td class="is-numeric">
                        <div class="row-actions">
                            <a href="{{ route('associates.statement', $associate) }}" class="btn btn-ghost btn-icon" title="Estado de cuenta" aria-label="Estado de cuenta">{{ icon('receipt', 'icon', 16) }}</a>
                            <a href="{{ route('portfolio.payments', ['q' => $associate->ruc ?: $associate->name]) }}" class="btn btn-ghost btn-icon" title="Historial de pagos" aria-label="Historial de pagos">{{ icon('wallet', 'icon', 16) }}</a>
                            <a href="{{ route('associates.show', $associate) }}" class="btn btn-ghost btn-icon" title="Ficha del asociado" aria-label="Ficha del asociado">{{ icon('users', 'icon', 16) }}</a>
                        </div>
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
