@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
        $firstName = explode(' ', auth()->user()->name)[0];
    @endphp

    <div class="page-header">
        <div>
            <h1>{{ $greeting }}, {{ $firstName }}</h1>
            <p>Aquí tienes un resumen de la gestión de cobranzas.</p>
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

    @php $userModules = auth()->user()->moduleCodes(); @endphp
    @if (in_array('reports', $userModules, true) || in_array('portfolio', $userModules, true))
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="chart-card">
                    <h2>Cobranza mensual</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Facturado vs. cobrado, últimos 6 meses</p>
                    <canvas id="collectionsChart" height="140" role="img" aria-label="Gráfico de facturado y cobrado por mes"></canvas>
                    <div class="chart-legend">
                        <span><span class="dot" style="background: var(--color-blue)"></span> Facturado</span>
                        <span><span class="dot" style="background: var(--color-teal)"></span> Cobrado</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="chart-card">
                    <h2>Distribución de cartera</h2>
                    <p class="text-secondary" style="font-size: var(--text-xs); margin-bottom: var(--space-4);">Facturas por estado</p>
                    <canvas id="portfolioChart" height="140" role="img" aria-label="Gráfico de distribución de facturas por estado"></canvas>
                    <div class="chart-legend">
                        <span><span class="dot" style="background: var(--color-success)"></span> Pagada</span>
                        <span><span class="dot" style="background: #92620A"></span> Parcial</span>
                        <span><span class="dot" style="background: var(--color-info)"></span> Pendiente</span>
                        <span><span class="dot" style="background: var(--color-danger)"></span> Vencida</span>
                    </div>
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
            @endmodule
            @module('billing')
                @can('billing.generate')
                    <a href="{{ route('invoices.create') }}" class="btn btn-secondary btn-sm">{{ icon('file-text', 'icon', 16) }} Generar facturación</a>
                @endcan
                <a href="{{ route('invoices.index') }}" class="btn btn-ghost btn-sm">{{ icon('file-text', 'icon', 16) }} Ver facturas</a>
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
    var monthly = @json($monthlyCollections);
    var distribution = @json($portfolioDistribution);

    var collectionsEl = document.getElementById('collectionsChart');
    if (collectionsEl && window.Chart) {
        new Chart(collectionsEl, {
            type: 'line',
            data: {
                labels: monthly.map(function (m) { return m.label; }),
                datasets: [
                    {
                        label: 'Facturado',
                        data: monthly.map(function (m) { return m.billed; }),
                        borderColor: '#2563EB',
                        backgroundColor: 'rgba(37, 99, 235, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                    },
                    {
                        label: 'Cobrado',
                        data: monthly.map(function (m) { return m.collected; }),
                        borderColor: '#14B8A6',
                        backgroundColor: 'rgba(20, 184, 166, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function (v) { return 'S/ ' + v; } }, grid: { color: '#F1F5F9' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    var portfolioEl = document.getElementById('portfolioChart');
    if (portfolioEl && window.Chart) {
        new Chart(portfolioEl, {
            type: 'doughnut',
            data: {
                labels: ['Pagada', 'Parcial', 'Pendiente', 'Vencida'],
                datasets: [{
                    data: [distribution.PAGADA, distribution.PARCIAL, distribution.PENDIENTE, distribution.VENCIDA],
                    backgroundColor: ['#16A34A', '#F59E0B', '#0284C7', '#DC2626'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                cutout: '68%',
                plugins: { legend: { display: false } }
            }
        });
    }
})();
</script>
@endpush
