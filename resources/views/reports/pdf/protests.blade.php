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
    <h1>Protestos y Moras</h1>
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
            <td><strong>Registros:</strong> {{ $totalCount }}</td>
            <td><strong>Monto cobrado:</strong> {{ format_money($totalAmount) }}</td>
            <td><strong>Regularizados:</strong> {{ $regularizedCount }}</td>
        </tr>
    </table>

    <x-pdf-bar-chart title="Monto por tipo" :categories="array_column($byType, 'label')" :values="array_column($byType, 'total')" />

    <x-pdf-bar-chart title="Registros por vía" :categories="array_column($byChannel, 'label')" :values="array_column($byChannel, 'count')" prefix="" />

    <table>
        <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Vía</th>
            <th>Deudor</th>
            <th>Acreedor</th>
            <th>Monto</th>
            <th>Estado</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($records as $record)
            <tr>
                <td>{{ format_date($record->registered_at) }}</td>
                <td>{{ $record->typeLabel() }}</td>
                <td>{{ $record->channelLabel() }}</td>
                <td>{{ $record->debtor_name }}</td>
                <td>{{ $record->creditor_name }}</td>
                <td>{{ format_money($record->amount) }}</td>
                <td>{{ $record->status }}</td>
            </tr>
        @empty
            <tr><td colspan="7">No se registraron protestos ni moras en este período.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr class="totals">
            <td colspan="5">Total cobrado</td>
            <td colspan="2">{{ format_money($totalAmount) }}</td>
        </tr>
        </tfoot>
    </table>
</body>
</html>
