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
    <h1>A quién falta cobrar</h1>
    <div class="meta">Generado: {{ now()->format('d/m/Y H:i') }}</div>

    @php
        $topDebtors = $associates
            ->map(fn ($a) => ['name' => $a->name, 'pending' => (float) ($a->total_invoiced ?? 0) - (float) ($a->total_paid ?? 0)])
            ->sortByDesc('pending')
            ->take(10);
    @endphp
    @if ($topDebtors->isNotEmpty())
        <x-pdf-bar-chart title="Top 10 — monto pendiente" :categories="$topDebtors->pluck('name')->all()" :values="$topDebtors->pluck('pending')->all()" />
    @endif

    <table>
        <thead>
        <tr>
            <th>Asociado</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Sectorista</th>
            <th>Monto pendiente</th>
            <th>Debe desde</th>
            <th>Pendientes</th>
            <th>Vencidas</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($associates as $associate)
            @php $pending = (float) ($associate->total_invoiced ?? 0) - (float) ($associate->total_paid ?? 0); @endphp
            <tr>
                <td>{{ $associate->name }}</td>
                <td>{{ $associate->legal_rep_phone ?: $associate->contact_phone ?: '-' }}</td>
                <td>{{ $associate->email ?: $associate->legal_rep_email ?: '-' }}</td>
                <td>{{ $associate->sectorista ?? '-' }}</td>
                <td>{{ format_money($pending) }}</td>
                <td>{{ $associate->oldest_pending_period ? format_period($associate->oldest_pending_period) : '-' }}</td>
                <td>{{ $associate->pending_invoices_count }}</td>
                <td>{{ $associate->overdue_invoices_count }}</td>
            </tr>
        @empty
            <tr><td colspan="8">Ningún asociado tiene deuda pendiente en este momento.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
