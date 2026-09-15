@php
    $activeFilters = array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== false);
    unset($activeFilters['sort']);
    $filterLabels = ['q' => 'Búsqueda', 'sectorista' => 'Sectorista', 'category' => 'Categoría', 'only_overdue' => 'Solo'];
    $pretty = fn ($key, $value) => $key === 'only_overdue' ? 'con facturas vencidas' : $value;
@endphp

@include('portfolio._filter_chips', ['activeFilters' => $activeFilters, 'filterLabels' => $filterLabels, 'pretty' => $pretty, 'route' => 'portfolio.debtors', 'count' => $associates->total(), 'noun' => 'deudor(es)'])

@if ($associates->isEmpty())
    <x-empty-state icon="check-circle-2" title="Sin deuda pendiente"
        :message="$activeFilters ? 'Ningún deudor coincide con los filtros seleccionados.' : 'Ningún asociado tiene deuda pendiente en este momento.'" />
@else
    <div class="table-wrap">
        <table class="data-table data-table-compact">
            <thead>
            <tr>
                <th>Asociado</th>
                <th>Contacto</th>
                <th>Sectorista</th>
                <th class="is-numeric">Monto pendiente</th>
                <th>Debe desde</th>
                <th class="is-numeric">Pendientes</th>
                <th class="is-numeric">Vencidas</th>
                <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($associates as $associate)
                @php
                    $pending = (float) ($associate->total_invoiced ?? 0) - (float) ($associate->total_paid ?? 0);
                    $phone = $associate->legal_rep_phone ?: $associate->contact_phone;
                    $email = $associate->email ?: $associate->legal_rep_email;
                @endphp
                <tr>
                    <td class="cell-primary cell-clamp">
                        <a href="{{ route('associates.statement', $associate) }}" class="link-plain">{{ $associate->name }}</a>
                        @if ($associate->legal_rep_name)
                            <div class="cell-muted" style="font-size: var(--text-xs); font-weight: 400;">{{ $associate->legal_rep_name }}</div>
                        @endif
                    </td>
                    <td class="cell-muted" style="font-size: var(--text-xs);">
                        @if ($phone)<div class="cell-nowrap">{{ icon('phone', 'icon', 12) }} {{ $phone }}</div>@endif
                        @if ($email)<div class="cell-email" title="{{ $email }}">{{ icon('mail', 'icon', 12) }} {{ $email }}</div>@endif
                        @if (! $phone && ! $email)-@endif
                    </td>
                    <td class="cell-muted cell-nowrap">{{ $associate->sectorista ?? '-' }}</td>
                    <td class="is-numeric cell-money cell-nowrap" style="color: var(--color-danger);">{{ format_money($pending) }}</td>
                    <td class="cell-nowrap">
                        {{ $associate->oldest_pending_period ? format_period($associate->oldest_pending_period) : '-' }}
                        <div class="cell-muted" style="font-size: var(--text-xs);">{{ $associate->oldest_pending_period ?? '' }}</div>
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
                            @can('billing.view')
                                <a href="{{ route('invoices.index', ['associate_id' => $associate->id, 'status' => 'no_pagadas']) }}" class="btn btn-ghost btn-icon" title="Facturas pendientes" aria-label="Facturas pendientes">{{ icon('file-text', 'icon', 16) }}</a>
                            @endcan
                        </div>
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
