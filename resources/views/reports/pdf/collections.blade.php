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
    <h1>Cobranza del período</h1>
    <div class="meta">Generado: {{ now()->format('d/m/Y H:i') }} — Período: {{ $period }}</div>

    <table class="kpis">
        <tr>
            <td><strong>Facturado del período:</strong> {{ format_money($totalInvoiced) }}</td>
            <td><strong>Cobrado del mes:</strong> {{ format_money($totalCollected) }}</td>
        </tr>
        <tr>
            <td><strong>Pagos registrados:</strong> {{ $paymentsCount }}</td>
            <td><strong>Asociados que pagaron:</strong> {{ $payingAssociatesCount }}</td>
        </tr>
    </table>

    <table>
        <thead>
        <tr>
            <th>Fecha</th>
            <th>Asociado</th>
            <th>Período factura</th>
            <th>Monto</th>
            <th>Registrado por</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($payments as $payment)
            <tr>
                <td>{{ format_date($payment->paid_at) }}</td>
                <td>{{ $payment->invoice->associate->name }}</td>
                <td>{{ $payment->invoice->period }}</td>
                <td>{{ format_money($payment->amount) }}</td>
                <td>{{ $payment->registeredBy->name ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No se registraron pagos en este período.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr class="totals">
            <td colspan="3">Total cobrado</td>
            <td colspan="2">{{ format_money($totalCollected) }}</td>
        </tr>
        </tfoot>
    </table>
</body>
</html>
