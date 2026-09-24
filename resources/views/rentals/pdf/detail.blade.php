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
        th { background: #f4f6f9; width: 32%; }
        .totals td { font-weight: bold; background: #f4f6f9; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 10px; background: #f4f6f9; font-weight: bold; }
        .footer-note { margin-top: 24px; color: #6c7a89; font-size: 10px; }
    </style>
</head>
<body>
    @php
        $heading = $rental->status === \App\Models\Rental::STATUS_COTIZADA ? 'Cotización de alquiler' : 'Comprobante de alquiler';
    @endphp
    <h1>{{ $heading }}</h1>
    <div class="meta">
        Cámara de Comercio de Huancayo — Generado: {{ now()->format('d/m/Y H:i') }} —
        Alquiler N.° {{ $rental->id }} — <span class="status">{{ $rental->statusLabel() }}</span>
    </div>

    <table>
        <tr><th>Espacio</th><td>{{ $rental->space->name }}</td></tr>
        <tr><th>Asociado</th><td>{{ $rental->associate->name }}{{ $rental->associate->ruc ? ' — RUC '.$rental->associate->ruc : '' }}</td></tr>
        <tr><th>Inicio</th><td>{{ $rental->starts_at->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Fin</th><td>{{ $rental->ends_at->format('d/m/Y H:i') }}</td></tr>
        @if ($rental->purpose)
            <tr><th>Motivo</th><td>{{ $rental->purpose }}</td></tr>
        @endif
        <tr class="totals"><th>Monto</th><td>{{ format_money($rental->amount) }}</td></tr>
    </table>

    @if ($rental->status === \App\Models\Rental::STATUS_COTIZADA)
        <p class="footer-note">Este documento es una cotización referencial — no constituye una reserva confirmada del espacio hasta que la Cámara la confirme.</p>
    @endif
</body>
</html>
