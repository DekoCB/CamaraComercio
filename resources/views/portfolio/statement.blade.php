@extends('layouts.app')

@section('title', 'Estado de cuenta — '.$associate->name)

@section('content')
    <x-page-header title="Estado de cuenta" :subtitle="$associate->name" />

    <div class="card-surface mb-3">
        <div class="d-flex align-items-center gap-3">
            <span class="avatar" style="width: 48px; height: 48px; font-size: 1rem;">
                {{ \Illuminate\Support\Str::of($associate->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
            </span>
            <div>
                <h2 class="text-h3">{{ $associate->name }}</h2>
                <div class="text-secondary" style="font-size: 0.8rem;">{{ $associate->company }}</div>
                <div class="text-secondary" style="font-size: 0.8rem;">{{ $associate->contact_phone ?? '-' }} · {{ $associate->email ?? '-' }}</div>
            </div>
            @unless ($associate->is_active)
                <span class="badge badge-neutral" style="margin-left: auto;">Inactivo</span>
            @endunless
        </div>
    </div>

    <div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: var(--space-4);">
        <x-kpi-card label="Total facturado" icon="file-text" variant="blue" :value="format_money($totalInvoiced)" />
        <x-kpi-card label="Total pagado" icon="wallet" variant="teal" :value="format_money($totalPaid)" />
        <x-kpi-card label="Saldo pendiente" icon="alert-triangle" variant="danger" :value="format_money($totalPending)" :critical="$totalPending > 0" />
    </div>

    <div class="card-surface">
        <h2 class="text-h3" style="margin-bottom: var(--space-4);">Historial de facturas</h2>
        @if ($invoices->isEmpty())
            <x-empty-state icon="file-text" title="Sin facturas" message="Este asociado todavía no tiene facturas." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Período</th>
                        <th class="is-numeric">Monto</th>
                        <th class="is-numeric">Pagado</th>
                        <th class="is-numeric">Saldo</th>
                        <th>Vence</th>
                        <th>Estado</th>
                        <th>Pagos</th>
                        <th class="is-numeric">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="cell-primary">{{ $invoice->period }}</td>
                            <td class="is-numeric cell-money">{{ format_money($invoice->amount) }}</td>
                            <td class="is-numeric cell-money">{{ format_money($invoice->paid_total) }}</td>
                            <td class="is-numeric cell-money">{{ format_money($invoice->balance()) }}</td>
                            <td class="cell-muted">{{ format_date($invoice->due_date) }}</td>
                            <td><x-status-badge :status="$invoice->effectiveStatus()" /></td>
                            <td class="cell-muted">{{ $invoice->payments->count() }}</td>
                            <td class="is-numeric">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">
                                    {{ icon('eye', 'icon', 15) }} Ver
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
