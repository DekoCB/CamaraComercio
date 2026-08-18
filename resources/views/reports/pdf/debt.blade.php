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
    <h1>Deuda pendiente</h1>
    <div class="meta">Generado: {{ now()->format('d/m/Y H:i') }}</div>

    <table class="kpis">
        <tr>
            <td><strong>Total pendiente:</strong> {{ format_money($totalPending) }}</td>
            <td><strong>Asociados con deuda:</strong> {{ $debtorsCount }}</td>
        </tr>
        <tr>
            <td><strong>Facturas pendientes:</strong> {{ $pendingInvoicesCount }}</td>
            <td><strong>Facturas vencidas:</strong> {{ $overdueInvoicesCount }}</td>
        </tr>
    </table>

    <table>
        <thead>
        <tr>
            <th>Estado</th>
            <th>Facturas</th>
            <th>Saldo</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($distribution as $row)
            <tr>
                <td>{{ $row->bucket }}</td>
                <td>{{ $row->invoice_count }}</td>
                <td>{{ format_money($row->total_balance) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No hay deuda pendiente en este momento.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr class="totals">
            <td>Total</td>
            <td>{{ $pendingInvoicesCount }}</td>
            <td>{{ format_money($totalPending) }}</td>
        </tr>
        </tfoot>
    </table>
</body>
</html>
