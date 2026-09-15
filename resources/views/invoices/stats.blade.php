@extends('layouts.app')

@section('title', 'Estadísticas de facturación')

@section('content')
    <x-page-header title="Estadísticas de facturación" subtitle="Cómo va la facturación y la cobranza: montos por mes, estado de las facturas, quién debe más y rendimiento por sectorista.">
        <x-slot:actions>
            <a href="{{ route('invoices.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver a facturas
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="table-card" style="margin-bottom: var(--space-4);">
        <form class="filter-bar filter-bar-grow" method="GET" action="{{ route('invoices.stats') }}"
              data-live-filter="#invoice-stats" role="search">
            <select name="year" class="form-select form-select-sm" aria-label="Año">
                <option value="">Año: todos</option>
                @foreach ($filterOptions['years'] as $year)
                    <option value="{{ $year }}" {{ $filters['year'] === (string) $year ? 'selected' : '' }}>{{ $year }}</option>
                @endforeach
            </select>

            <select name="month" class="form-select form-select-sm" aria-label="Mes">
                <option value="">Mes: todos</option>
                @foreach ($months as $number => $label)
                    <option value="{{ $number }}" {{ $filters['month'] === $number ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            @foreach (['sectorista' => 'Sectorista', 'category' => 'Categoría'] as $key => $label)
                @if ($filterOptions[$key] !== [])
                    <select name="{{ $key }}" class="form-select form-select-sm" aria-label="{{ $label }}">
                        <option value="">{{ $label }}: todos</option>
                        @foreach ($filterOptions[$key] as $option)
                            <option value="{{ $option }}" {{ $filters[$key] === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                @endif
            @endforeach

            <button type="submit" class="btn btn-secondary btn-sm">{{ icon('filter', 'icon', 15) }} Aplicar</button>
            <a href="{{ route('invoices.stats', ['year' => '']) }}" class="btn btn-link btn-sm js-live-clear" {{ array_filter($filters, fn ($v) => $v !== null && $v !== '') ? '' : 'hidden' }}>Limpiar</a>
        </form>
    </div>

    <div id="invoice-stats" class="live-results">
        @include('invoices._stats')
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
(function () {
    var COLORS = { blue: '#2563EB', teal: '#14B8A6', navy: '#0F2747', success: '#16A34A', warning: '#F59E0B', info: '#0284C7', danger: '#DC2626', muted: '#94A3B8' };
    var money = function (v) { return 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var charts = [];

    function render() {
        var dataEl = document.getElementById('invoice-stats-data');
        if (!dataEl || !window.Chart) {
            return;
        }
        charts.forEach(function (c) { c.destroy(); });
        charts = [];
        var stats = JSON.parse(dataEl.textContent);
        var baseOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.dataset.label + ': ' + money(ctx.parsed.y !== undefined && ctx.parsed.y !== null ? ctx.parsed.y : ctx.parsed.x); } } } } };

        var monthlyEl = document.getElementById('statsMonthlyChart');
        if (monthlyEl) {
            charts.push(new Chart(monthlyEl, {
                type: 'bar',
                data: {
                    labels: stats.monthly.map(function (m) { return m.label; }),
                    datasets: [
                        { label: 'Facturado', data: stats.monthly.map(function (m) { return m.billed; }), backgroundColor: COLORS.blue, borderRadius: 4 },
                        { label: 'Cobrado', data: stats.monthly.map(function (m) { return m.paid; }), backgroundColor: COLORS.teal, borderRadius: 4 },
                        { label: 'Pendiente', data: stats.monthly.map(function (m) { return m.balance; }), backgroundColor: COLORS.danger, borderRadius: 4 }
                    ]
                },
                options: Object.assign({}, baseOptions, {
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: function (v) { return 'S/ ' + v; } }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        x: { grid: { display: false } }
                    }
                })
            }));
        }

        var statusEl = document.getElementById('statsStatusChart');
        if (statusEl) {
            var s = stats.by_status;
            charts.push(new Chart(statusEl, {
                type: 'doughnut',
                data: {
                    labels: ['Pagadas', 'Pago parcial', 'Pendientes', 'Vencidas'],
                    datasets: [{ data: [s.PAGADA.count, s.PARCIAL.count, s.PENDIENTE.count, s.VENCIDA.count], backgroundColor: [COLORS.success, COLORS.warning, COLORS.info, COLORS.danger], borderWidth: 0 }]
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed + ' factura(s)'; } } } } }
            }));
        }

        var debtorsEl = document.getElementById('statsDebtorsChart');
        if (debtorsEl) {
            charts.push(new Chart(debtorsEl, {
                type: 'bar',
                data: {
                    labels: stats.top_debtors.map(function (d) { return d.name.length > 28 ? d.name.slice(0, 27) + '…' : d.name; }),
                    datasets: [{ label: 'Saldo pendiente', data: stats.top_debtors.map(function (d) { return d.balance; }), backgroundColor: COLORS.danger, borderRadius: 4 }]
                },
                options: Object.assign({}, baseOptions, {
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, ticks: { callback: function (v) { return 'S/ ' + v; } }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                })
            }));
        }

        var sectorEl = document.getElementById('statsSectoristaChart');
        if (sectorEl) {
            charts.push(new Chart(sectorEl, {
                type: 'bar',
                data: {
                    labels: stats.by_sectorista.map(function (r) { return r.name; }),
                    datasets: [
                        { label: 'Cobrado', data: stats.by_sectorista.map(function (r) { return r.paid; }), backgroundColor: COLORS.teal, borderRadius: 4 },
                        { label: 'Pendiente', data: stats.by_sectorista.map(function (r) { return r.balance; }), backgroundColor: COLORS.danger, borderRadius: 4 }
                    ]
                },
                options: Object.assign({}, baseOptions, {
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, beginAtZero: true, ticks: { callback: function (v) { return 'S/ ' + v; } }, grid: { color: 'rgba(148,163,184,0.15)' } }
                    }
                })
            }));
        }
    }

    render();
    var container = document.getElementById('invoice-stats');
    if (container) {
        container.addEventListener('live-filter:loaded', render);
    }
})();
</script>
@endpush
