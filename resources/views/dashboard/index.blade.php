@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
        $firstName = explode(' ', auth()->user()->name)[0];
        $userModules = auth()->user()->moduleCodes();
        $showBilling = in_array('reports', $userModules, true) || in_array('portfolio', $userModules, true) || in_array('billing', $userModules, true);
        $showAssociates = in_array('associates', $userModules, true);
    @endphp

    <div class="page-header">
        <div>
            <h1>{{ $greeting }}, {{ $firstName }}</h1>
            <p>Aquí tienes un resumen general de la Cámara: asociados, facturación y cobranza.</p>
        </div>
    </div>

    <div class="kpi-grid">
        <x-kpi-card label="Asociados" icon="users" variant="navy" :value="$totalAssociates" footnote="Total registrados" />
        <x-kpi-card label="Facturado" icon="file-text" variant="blue" :value="format_money($billedThisPeriod)" footnote="Período actual" />
        <x-kpi-card label="Cobrado" icon="wallet" variant="teal" :value="format_money($collectedThisMonth)" :trend="$collectedTrend" />
        <x-kpi-card label="Pendiente" icon="alert-triangle" variant="danger" :value="format_money($pendingBalance)"
                    :critical="$pendingBalance > 0"
                    :footnote="$overdueCount > 0 ? $overdueCount.' factura(s) vencida(s)' : 'Sin facturas vencidas'" />
    </div>

    <div class="kpi-grid kpi-grid-compact">
        <x-kpi-card label="Asociados activos" icon="check-circle-2" variant="teal" :value="number_format($associatesByStatus['ACTIVO'])"
                    :footnote="$associatesByStatus['SUSPENDIDO'].' suspendidos · '.$associatesByStatus['DESAFILIADO'].' desafiliados'" />
        <x-kpi-card label="Cobranza del año" icon="trending-up" variant="blue" :value="$yearCollectionRate !== null ? number_format($yearCollectionRate, 1).' %' : '—'"
                    :footnote="'De lo facturado en '.now()->format('Y')" />
        <x-kpi-card label="Cuota mensual esperada" icon="receipt" variant="navy" :value="format_money($expectedMonthlyFees)"
                    :footnote="$associatesWithFee.' activos con monto propio'" />
        <x-kpi-card label="Nuevos asociados" icon="user-round-plus" variant="warning" :value="number_format($newAssociatesThisYear)"
                    :footnote="'Ingresaron en '.now()->format('Y')" />
    </div>

    @if ($showBilling)
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card h-100">
                    <h2>Cobranza mensual</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Facturado vs. cobrado, últimos 12 meses</p>
                    <div class="chart-box chart-box-lg"><canvas id="collectionsChart" role="img" aria-label="Gráfico de facturado y cobrado por mes"></canvas></div>
                    <div class="chart-legend">
                        <span><span class="dot" style="background: var(--color-blue)"></span> Facturado</span>
                        <span><span class="dot" style="background: var(--color-teal)"></span> Cobrado</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card h-100">
                    <h2>Distribución de cartera</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Facturas por estado</p>
                    <div class="chart-box"><canvas id="portfolioChart" role="img" aria-label="Gráfico de distribución de facturas por estado"></canvas></div>
                    <div class="chart-legend chart-legend-grid">
                        <span><span class="dot" style="background: var(--color-success)"></span> Pagada <strong>{{ $portfolioDistribution['PAGADA'] }}</strong></span>
                        <span><span class="dot" style="background: #F59E0B"></span> Parcial <strong>{{ $portfolioDistribution['PARCIAL'] }}</strong></span>
                        <span><span class="dot" style="background: var(--color-info)"></span> Pendiente <strong>{{ $portfolioDistribution['PENDIENTE'] }}</strong></span>
                        <span><span class="dot" style="background: var(--color-danger)"></span> Vencida <strong>{{ $portfolioDistribution['VENCIDA'] }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="chart-card h-100">
                    <h2>Tasa de cobranza por mes</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Porcentaje de lo facturado en cada período que ya se cobró</p>
                    <div class="chart-box"><canvas id="rateChart" role="img" aria-label="Tasa de cobranza mensual"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-card h-100">
                    <h2>Asociados con mayor deuda</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Saldo pendiente acumulado, de mayor a menor</p>
                    @if ($topDebtors === [])
                        <p class="cell-muted" style="font-size: 0.875rem;">Ningún asociado tiene saldo pendiente.</p>
                    @else
                        <div class="chart-box"><canvas id="debtorsChart" role="img" aria-label="Asociados con mayor saldo pendiente"></canvas></div>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="chart-card h-100">
                    <h2>Cartera por sectorista</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Cobrado y pendiente en la cartera de cada sectorista</p>
                    <div class="chart-box"><canvas id="sectoristaChart" role="img" aria-label="Cobrado y pendiente por sectorista"></canvas></div>
                    <div class="chart-legend">
                        <span><span class="dot" style="background: var(--color-teal)"></span> Cobrado</span>
                        <span><span class="dot" style="background: var(--color-danger)"></span> Pendiente</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-card h-100">
                    <h2>Asociados por categoría</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Cantidad de asociados activos y cuota mensual que suman por categoría</p>
                    <div class="chart-box"><canvas id="categoryChart" role="img" aria-label="Asociados y cuota mensual por categoría"></canvas></div>
                    <div class="chart-legend">
                        <span><span class="dot" style="background: var(--color-navy)"></span> Asociados</span>
                        <span><span class="dot" style="background: var(--color-blue)"></span> Cuota mensual (S/)</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showAssociates)
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="chart-card h-100">
                    <h2>Asociados por estado</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Activos, suspendidos y desafiliados</p>
                    <div class="chart-box"><canvas id="statusChart" role="img" aria-label="Asociados por estado"></canvas></div>
                    <div class="chart-legend chart-legend-grid">
                        <span><span class="dot" style="background: var(--color-success)"></span> Activos <strong>{{ $associatesByStatus['ACTIVO'] }}</strong></span>
                        <span><span class="dot" style="background: #F59E0B"></span> Suspendidos <strong>{{ $associatesByStatus['SUSPENDIDO'] }}</strong></span>
                        <span><span class="dot" style="background: #94A3B8"></span> Desafiliados <strong>{{ $associatesByStatus['DESAFILIADO'] }}</strong></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card h-100">
                    <h2>Asociados por actividad</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Según su actividad (comercio, servicio, industria…)</p>
                    <div class="chart-box"><canvas id="activityChart" role="img" aria-label="Asociados por actividad"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card h-100">
                    <h2>Crecimiento de asociados</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Ingresos por año (según fecha de ingreso) y total acumulado</p>
                    @if ($associatesGrowth === [])
                        <p class="cell-muted" style="font-size: 0.875rem;">Aún no hay fechas de ingreso registradas.</p>
                    @else
                        <div class="chart-box"><canvas id="growthChart" role="img" aria-label="Nuevos asociados por año"></canvas></div>
                        <div class="chart-legend">
                            <span><span class="dot" style="background: var(--color-navy)"></span> Nuevos</span>
                            <span><span class="dot" style="background: var(--color-teal)"></span> Acumulado</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="table-card">
        <h2 class="text-h3" style="margin-bottom: var(--space-4);">Accesos rápidos</h2>
        <div class="d-flex flex-wrap gap-2">
            @module('associates')
                @can('associates.manage')
                    <a href="{{ route('associates.create') }}" class="btn btn-secondary btn-sm">{{ icon('users', 'icon', 16) }} Registrar asociado</a>
                @endcan
                <a href="{{ route('associates.index') }}" class="btn btn-ghost btn-sm">{{ icon('users', 'icon', 16) }} Ver asociados</a>
                <a href="{{ route('associates.birthdays') }}" class="btn btn-ghost btn-sm">{{ icon('cake', 'icon', 16) }} Cumpleaños</a>
            @endmodule
            @module('billing')
                @can('billing.generate')
                    <a href="{{ route('invoices.create') }}" class="btn btn-secondary btn-sm js-modal-link" data-modal-title="Generar facturación del mes">{{ icon('file-text', 'icon', 16) }} Generar facturación</a>
                @endcan
                <a href="{{ route('invoices.index') }}" class="btn btn-ghost btn-sm">{{ icon('file-text', 'icon', 16) }} Ver facturas</a>
                @can('billing.view')
                    <a href="{{ route('invoices.stats') }}" class="btn btn-ghost btn-sm">{{ icon('bar-chart-3', 'icon', 16) }} Estadísticas de facturación</a>
                @endcan
            @endmodule
            @module('payments')
                @can('payments.register')
                    <a href="{{ route('payments.index') }}" class="btn btn-ghost btn-sm">{{ icon('wallet', 'icon', 16) }} Ver pagos</a>
                @endcan
            @endmodule
            @module('portfolio')
                @can('portfolio.view')
                    <a href="{{ route('portfolio.debtors') }}" class="btn btn-ghost btn-sm">{{ icon('trending-up', 'icon', 16) }} A quién falta cobrar</a>
                @endcan
            @endmodule
            @module('reports')
                @can('reports.view')
                    <a href="{{ route('reports.index') }}" class="btn btn-ghost btn-sm">{{ icon('bar-chart-3', 'icon', 16) }} Reportes</a>
                @endcan
            @endmodule
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
(function () {
    if (!window.Chart) { return; }
    var C = { blue: '#2563EB', teal: '#14B8A6', navy: '#0F2747', success: '#16A34A', warning: '#F59E0B', info: '#0284C7', danger: '#DC2626', muted: '#94A3B8', purple: '#7C3AED', pink: '#DB2777' };
    var PALETTE = [C.blue, C.teal, C.warning, C.purple, C.pink, C.success, C.info, C.muted];
    var money = function (v) { return 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var grid = { color: 'rgba(148,163,184,0.15)' };
    var noLegend = { legend: { display: false } };
    var moneyTip = { callbacks: { label: function (ctx) { var v = ctx.parsed.y !== undefined && ctx.parsed.y !== null ? ctx.parsed.y : ctx.parsed.x; return ' ' + ctx.dataset.label + ': ' + money(v); } } };
    function make(id, config) { var el = document.getElementById(id); if (el) { new Chart(el, config); } }

    var monthly = @json($monthlyCollections);
    make('collectionsChart', {
        type: 'line',
        data: { labels: monthly.map(function (m) { return m.label; }), datasets: [
            { label: 'Facturado', data: monthly.map(function (m) { return m.billed; }), borderColor: C.blue, backgroundColor: 'rgba(37, 99, 235, 0.08)', fill: true, tension: 0.35, pointRadius: 3 },
            { label: 'Cobrado', data: monthly.map(function (m) { return m.collected; }), borderColor: C.teal, backgroundColor: 'rgba(20, 184, 166, 0.08)', fill: true, tension: 0.35, pointRadius: 3 }
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: moneyTip },
            scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return 'S/ ' + v; } }, grid: grid }, x: { grid: { display: false } } } }
    });

    var distribution = @json($portfolioDistribution);
    make('portfolioChart', {
        type: 'doughnut',
        data: { labels: ['Pagada', 'Parcial', 'Pendiente', 'Vencida'], datasets: [{ data: [distribution.PAGADA, distribution.PARCIAL, distribution.PENDIENTE, distribution.VENCIDA], backgroundColor: [C.success, C.warning, C.info, C.danger], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: noLegend }
    });

    var rate = @json($collectionRateByMonth);
    make('rateChart', {
        type: 'line',
        data: { labels: rate.map(function (m) { return m.label; }), datasets: [{ label: 'Cobrado', data: rate.map(function (m) { return m.rate; }), borderColor: C.teal, backgroundColor: 'rgba(20, 184, 166, 0.12)', fill: true, tension: 0.3, pointRadius: 3, spanGaps: true }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { var m = rate[ctx.dataIndex]; return ctx.parsed.y === null ? ' Sin facturación' : ' ' + ctx.parsed.y + ' % (' + money(m.paid) + ' de ' + money(m.billed) + ')'; } } } },
            scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + ' %'; } }, grid: grid }, x: { grid: { display: false } } } }
    });

    var debtors = @json($topDebtors);
    make('debtorsChart', {
        type: 'bar',
        data: { labels: debtors.map(function (d) { return d.name.length > 26 ? d.name.slice(0, 25) + '…' : d.name; }), datasets: [{ label: 'Saldo pendiente', data: debtors.map(function (d) { return d.balance; }), backgroundColor: C.danger, borderRadius: 4 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: moneyTip },
            scales: { x: { beginAtZero: true, ticks: { callback: function (v) { return 'S/ ' + v; } }, grid: grid }, y: { grid: { display: false }, ticks: { font: { size: 11 } } } } }
    });

    var sectorista = @json($portfolioBySectorista);
    make('sectoristaChart', {
        type: 'bar',
        data: { labels: sectorista.map(function (r) { return r.name; }), datasets: [
            { label: 'Cobrado', data: sectorista.map(function (r) { return r.paid; }), backgroundColor: C.teal, borderRadius: 4 },
            { label: 'Pendiente', data: sectorista.map(function (r) { return r.balance; }), backgroundColor: C.danger, borderRadius: 4 }
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: moneyTip },
            scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { callback: function (v) { return 'S/ ' + v; } }, grid: grid } } }
    });

    var category = @json($associatesByCategory);
    make('categoryChart', {
        type: 'bar',
        data: { labels: category.map(function (r) { return r.name; }), datasets: [
            { label: 'Asociados', data: category.map(function (r) { return r.count; }), backgroundColor: C.navy, borderRadius: 4, yAxisID: 'y' },
            { label: 'Cuota mensual', data: category.map(function (r) { return r.fees; }), backgroundColor: C.blue, borderRadius: 4, yAxisID: 'y1' }
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.dataset.label + ': ' + (ctx.datasetIndex === 0 ? ctx.parsed.y : money(ctx.parsed.y)); } } } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, position: 'left', ticks: { precision: 0 }, grid: grid }, y1: { beginAtZero: true, position: 'right', ticks: { callback: function (v) { return 'S/ ' + v; } }, grid: { drawOnChartArea: false } } } }
    });

    var status = @json($associatesByStatus);
    make('statusChart', {
        type: 'doughnut',
        data: { labels: ['Activos', 'Suspendidos', 'Desafiliados'], datasets: [{ data: [status.ACTIVO, status.SUSPENDIDO, status.DESAFILIADO], backgroundColor: [C.success, C.warning, C.muted], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: noLegend }
    });

    var activity = @json($associatesByActivity);
    make('activityChart', {
        type: 'pie',
        data: { labels: activity.map(function (r) { return r.name; }), datasets: [{ data: activity.map(function (r) { return r.count; }), backgroundColor: PALETTE, borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
    });

    var growth = @json($associatesGrowth);
    make('growthChart', {
        type: 'bar',
        data: { labels: growth.map(function (g) { return g.year; }), datasets: [
            { type: 'bar', label: 'Nuevos', data: growth.map(function (g) { return g.joined; }), backgroundColor: C.navy, borderRadius: 4, yAxisID: 'y' },
            { type: 'line', label: 'Acumulado', data: growth.map(function (g) { return g.cumulative; }), borderColor: C.teal, backgroundColor: C.teal, tension: 0.3, pointRadius: 2, yAxisID: 'y1' }
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: noLegend,
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: grid }, y1: { beginAtZero: true, position: 'right', ticks: { precision: 0 }, grid: { drawOnChartArea: false } } } }
    });
})();
</script>
@endpush
