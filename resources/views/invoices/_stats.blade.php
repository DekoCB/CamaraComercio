@php
    $s = $stats['summary'];
    $byStatus = $stats['by_status'];
    $scopeLabel = collect([
        $filters['year'] ? 'Año '.$filters['year'] : 'Todos los años',
        $filters['month'] ? $months[$filters['month']] : null,
        $filters['sectorista'] ? 'Sectorista '.$filters['sectorista'] : null,
        $filters['category'] ? 'Categoría '.$filters['category'] : null,
    ])->filter()->implode(' · ');
@endphp

<script type="application/json" id="invoice-stats-data">{!! json_encode($stats) !!}</script>

<p class="cell-muted" style="font-size: var(--text-xs); margin: 0 0 var(--space-3);">
    {{ $scopeLabel }} — {{ number_format($s['total']) }} factura{{ $s['total'] === 1 ? '' : 's' }} de {{ number_format($s['associates']) }} asociado{{ $s['associates'] === 1 ? '' : 's' }}
</p>

<div class="kpi-grid kpi-grid-compact">
    <x-kpi-card label="Facturado" :value="format_money($s['billed'])" icon="receipt" variant="navy"
                :footnote="number_format($s['total']).' facturas emitidas'" />
    <x-kpi-card label="Cobrado" :value="format_money($s['paid'])" icon="check-circle-2" variant="teal"
                :footnote="number_format($s['collection_rate'], 1).' % de lo facturado · '.number_format($s['paid_count']).' pagadas'" />
    <x-kpi-card label="Pendiente de cobro" :value="format_money($s['balance'])" icon="clock" variant="warning"
                :footnote="number_format($s['unpaid_count']).' facturas con saldo'" />
    <x-kpi-card label="Vencido" :value="format_money($s['overdue_balance'])" icon="alert-triangle" variant="danger"
                :critical="$s['overdue_count'] > 0" :footnote="number_format($s['overdue_count']).' facturas vencidas'" />
</div>

@if ($s['total'] === 0)
    <x-empty-state icon="bar-chart-3" title="Sin datos para graficar" message="No hay facturas que coincidan con los filtros seleccionados." />
@else
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="chart-card h-100">
                <h2>Facturado vs. cobrado por mes</h2>
                <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-3);">Monto emitido, lo efectivamente cobrado y el saldo pendiente de cada período.</p>
                <div class="chart-box chart-box-lg"><canvas id="statsMonthlyChart" role="img" aria-label="Facturado, cobrado y pendiente por mes"></canvas></div>
                <div class="chart-legend">
                    <span><span class="dot" style="background: #2563EB"></span> Facturado</span>
                    <span><span class="dot" style="background: #14B8A6"></span> Cobrado</span>
                    <span><span class="dot" style="background: #DC2626"></span> Pendiente</span>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card h-100">
                <h2>Facturas por estado</h2>
                <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-3);">Cantidad de facturas según su situación actual.</p>
                <div class="chart-box"><canvas id="statsStatusChart" role="img" aria-label="Distribución de facturas por estado"></canvas></div>
                <div class="chart-legend chart-legend-grid">
                    <span><span class="dot" style="background: #16A34A"></span> Pagadas <strong>{{ $byStatus['PAGADA']['count'] }}</strong></span>
                    <span><span class="dot" style="background: #F59E0B"></span> Pago parcial <strong>{{ $byStatus['PARCIAL']['count'] }}</strong></span>
                    <span><span class="dot" style="background: #0284C7"></span> Pendientes <strong>{{ $byStatus['PENDIENTE']['count'] }}</strong></span>
                    <span><span class="dot" style="background: #DC2626"></span> Vencidas <strong>{{ $byStatus['VENCIDA']['count'] }}</strong></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="chart-card h-100">
                <h2>Asociados con mayor deuda</h2>
                <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-3);">Los 10 con más saldo pendiente en el período seleccionado.</p>
                @if ($stats['top_debtors'] === [])
                    <p class="cell-muted" style="font-size: 0.875rem;">Nadie tiene saldo pendiente. 🎉</p>
                @else
                    <div class="chart-box chart-box-lg"><canvas id="statsDebtorsChart" role="img" aria-label="Asociados con mayor saldo pendiente"></canvas></div>
                @endif
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card h-100">
                <h2>Cobranza por sectorista</h2>
                <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-3);">Cuánto se ha cobrado y cuánto falta en la cartera de cada sectorista.</p>
                <div class="chart-box chart-box-lg"><canvas id="statsSectoristaChart" role="img" aria-label="Cobrado y pendiente por sectorista"></canvas></div>
                <div class="chart-legend">
                    <span><span class="dot" style="background: #14B8A6"></span> Cobrado</span>
                    <span><span class="dot" style="background: #DC2626"></span> Pendiente</span>
                </div>
                <table class="data-table data-table-compact" style="margin-top: var(--space-3);">
                    <thead><tr><th>Sectorista</th><th class="is-numeric">Facturas</th><th class="is-numeric">Facturado</th><th class="is-numeric">Cobrado</th><th class="is-numeric">Pendiente</th></tr></thead>
                    <tbody>
                    @foreach ($stats['by_sectorista'] as $row)
                        <tr>
                            <td class="cell-primary">{{ $row['name'] }}</td>
                            <td class="is-numeric cell-muted">{{ $row['count'] }}</td>
                            <td class="is-numeric">{{ format_money($row['billed']) }}</td>
                            <td class="is-numeric">{{ format_money($row['paid']) }}</td>
                            <td class="is-numeric {{ $row['balance'] > 0 ? 'text-danger' : '' }}">{{ format_money($row['balance']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
