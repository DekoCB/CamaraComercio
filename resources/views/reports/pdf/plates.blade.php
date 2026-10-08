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
    <h1>Placas</h1>
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
            <td><strong>Trámites:</strong> {{ $totalCount }}</td>
            <td><strong>Monto cobrado:</strong> {{ format_money($totalAmount) }}</td>
        </tr>
    </table>

    <x-pdf-bar-chart title="Monto por tipo de trámite" :categories="array_column($byProcedure, 'label')" :values="array_column($byProcedure, 'total')" />

    <x-pdf-bar-chart title="Trámites por comprobante" :categories="array_column($byReceiptType, 'label')" :values="array_column($byReceiptType, 'count')" prefix="" />

    <table>
        <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Placa</th>
            <th>Solicitante</th>
            <th>Comprobante</th>
            <th>Costo</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($records as $record)
            <tr>
                <td>{{ format_date($record->issued_at) }}</td>
                <td>{{ $record->procedureLabel() }}</td>
                <td>{{ $record->plate_number ?? '-' }}</td>
                <td>{{ $record->requesterLabel() }}</td>
                <td>{{ $record->receiptLabel() }}</td>
                <td>{{ format_money($record->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No se registraron trámites de placas en este período.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr class="totals">
            <td colspan="5">Total cobrado</td>
            <td>{{ format_money($totalAmount) }}</td>
        </tr>
        </tfoot>
    </table>
</body>
</html>
