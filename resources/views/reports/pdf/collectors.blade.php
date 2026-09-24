<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #22303f; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .meta { color: #6c7a89; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #dee2e6; padding: 6px 8px; text-align: left; }
        th { background: #f4f6f9; }
        .totals td { font-weight: bold; background: #f4f6f9; }
        .kpis { width: 100%; margin-bottom: 12px; }
        .kpis td { border: none; padding: 4px 12px 4px 0; }
    </style>
</head>
<body>
    <h1>Productividad por cobrador</h1>
    <div class="meta">
        Generado: {{ now()->format('d/m/Y H:i') }} —
        @if ($isRange)
            Del {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }}
        @else
            Período: {{ $period }}
        @endif
    </div>

    <table class="kpis">
        <tr>
            <td><strong>Total cobrado:</strong> {{ format_money($totalCollected) }}</td>
            <td><strong>Pagos registrados:</strong> {{ $paymentsCount }}</td>
            <td><strong>Cobradores con movimientos:</strong> {{ $collectorsCount }}</td>
        </tr>
    </table>

    <x-pdf-bar-chart title="Total cobrado por cobrador" :categories="array_column($byCollector, 'name')" :values="array_column($byCollector, 'total')" />

    <table>
        <thead>
        <tr>
            <th>Cobrador</th>
            <th>Pagos</th>
            <th>Total cobrado</th>
            <th>Promedio por pago</th>
            <th>% del total</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($byCollector as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['count'] }}</td>
                <td>{{ format_money($row['total']) }}</td>
                <td>{{ format_money($row['average']) }}</td>
                <td>{{ $row['share'] }}%</td>
            </tr>
        @empty
            <tr><td colspan="5">No se registraron pagos en este período.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr class="totals">
            <td colspan="2">Total</td>
            <td colspan="3">{{ format_money($totalCollected) }}</td>
        </tr>
        </tfoot>
    </table>
</body>
</html>
