<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #22303f; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .meta { color: #6c7a89; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #dee2e6; padding: 5px 7px; text-align: left; }
        th { background: #f4f6f9; }
        .totals td { font-weight: bold; background: #f4f6f9; }
    </style>
</head>
<body>
    <h1>Cartera por asociado</h1>
    <div class="meta">Generado: {{ now()->format('d/m/Y H:i') }}</div>

    <table>
        <thead>
        <tr>
            <th>Asociado</th>
            <th>RUC</th>
            <th>Sectorista</th>
            <th>Facturado</th>
            <th>Pagado</th>
            <th>Pendiente</th>
            <th>Pendientes</th>
            <th>Vencidas</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($associates as $associate)
            @php
                $invoiced = (float) ($associate->total_invoiced ?? 0);
                $paid = (float) ($associate->total_paid ?? 0);
            @endphp
            <tr>
                <td>{{ $associate->name }}</td>
                <td>{{ $associate->ruc ?? '-' }}</td>
                <td>{{ $associate->sectorista ?? '-' }}</td>
                <td>{{ format_money($invoiced) }}</td>
                <td>{{ format_money($paid) }}</td>
                <td>{{ format_money($invoiced - $paid) }}</td>
                <td>{{ $associate->pending_invoices_count }}</td>
                <td>{{ $associate->overdue_invoices_count }}</td>
            </tr>
        @empty
            <tr><td colspan="8">Sin asociados para los filtros seleccionados.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
