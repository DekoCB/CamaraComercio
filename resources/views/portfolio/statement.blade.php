@extends('layouts.app')

@section('title', 'Estado de cuenta — '.$associate->name)

@php
    $validPayments = $payments->filter(fn ($p) => ! $p->isVoided());
    $percent = $totalInvoiced > 0 ? min(100, round($totalPaid / $totalInvoiced * 100)) : 0;
@endphp

@section('content')
    <x-page-header title="Estado de cuenta" :subtitle="$associate->name.($year ? ' · Año '.$year : ' · Historial completo')">
        <x-slot:actions>
            <a href="{{ route('portfolio.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Cartera
            </a>
            <a href="{{ route('associates.show', $associate) }}" class="btn btn-secondary btn-sm">
                {{ icon('users', 'icon', 16) }} Ficha del asociado
            </a>
            @can('billing.view')
                <a href="{{ route('invoices.index', ['associate_id' => $associate->id]) }}" class="btn btn-secondary btn-sm">
                    {{ icon('file-text', 'icon', 16) }} Facturas
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface mb-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="avatar" style="width: 48px; height: 48px; font-size: 1rem;">
                {{ \Illuminate\Support\Str::of($associate->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
            </span>
            <div style="flex: 1 1 260px;">
                <h2 class="text-h3" style="margin: 0;">{{ $associate->name }}</h2>
                <div class="invoice-hero-meta">
                    @if ($associate->company && $associate->company !== $associate->name)<span>{{ $associate->company }}</span>@endif
                    @if ($associate->ruc)<span>RUC {{ $associate->ruc }}</span>@endif
                    @include('associates._status_badge', ['status' => $associate->status])
                    @if ($associate->sectorista)<span class="badge badge-neutral">Sectorista: {{ $associate->sectorista }}</span>@endif
                    @if ($associate->category)<span class="badge badge-neutral">Cat. {{ $associate->category }}{{ $associate->monthly_fee !== null ? ' · '.format_money($associate->monthly_fee).'/mes' : '' }}</span>@endif
                </div>
                <div class="invoice-hero-meta" style="margin-top: 4px;">
                    @if ($associate->contact_phone)<span>{{ icon('phone', 'icon', 12) }} {{ $associate->contact_phone }}</span>@endif
                    @if ($associate->email)<span>{{ icon('mail', 'icon', 12) }} {{ $associate->email }}</span>@endif
                    @if ($associate->legal_rep_name)<span>Rep. legal: {{ $associate->legal_rep_name }}{{ $associate->legal_rep_phone ? ' · '.$associate->legal_rep_phone : '' }}</span>@endif
                </div>
            </div>
            <form method="GET" action="{{ route('associates.statement', $associate) }}" class="filter-bar">
                <select name="year" class="form-select form-select-sm" aria-label="Año" onchange="this.form.submit()">
                    <option value="">Todo el historial</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}" {{ $year === (string) $y ? 'selected' : '' }}>Año {{ $y }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <div class="kpi-grid kpi-grid-compact">
        <x-kpi-card label="Total facturado" icon="file-text" variant="blue" :value="format_money($totalInvoiced)" :footnote="$invoices->count().' cuota(s)'" />
        <x-kpi-card label="Total pagado" icon="wallet" variant="teal" :value="format_money($totalPaid)" :footnote="$percent.' % de lo facturado · '.$validPayments->count().' pago(s)'" />
        <x-kpi-card label="Saldo pendiente" icon="clock" variant="warning" :value="format_money($totalPending)" :footnote="$invoices->filter(fn ($i) => $i->balance() > 0)->count().' cuota(s) con saldo'" />
        <x-kpi-card label="Vencidas" icon="alert-triangle" variant="danger" :value="number_format($overdueCount)" :critical="$overdueCount > 0" footnote="Con saldo y fecha vencida" />
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card-surface h-100">
                <h2 class="text-h3" style="margin-bottom: var(--space-3);">Cuotas</h2>
                @if ($invoices->isEmpty())
                    <x-empty-state icon="file-text" title="Sin facturas" message="Este asociado no tiene facturas en el período seleccionado." />
                @else
                    <div class="table-wrap">
                        <table class="data-table data-table-compact">
                            <thead>
                            <tr>
                                <th>Período</th>
                                <th class="is-numeric">Monto</th>
                                <th class="is-numeric">Pagado</th>
                                <th class="is-numeric">Saldo</th>
                                <th>Vence</th>
                                <th>Estado</th>
                                <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($invoices as $invoice)
                                <tr class="{{ $invoice->isVoided() ? 'row-voided' : '' }}">
                                    <td class="cell-primary cell-nowrap">
                                        {{ format_period($invoice->period) }}
                                        <div class="cell-muted" style="font-size: var(--text-xs); font-weight: 400;">{{ $invoice->period }}{{ $invoice->receipt_number ? ' · '.$invoice->receipt_number : '' }}</div>
                                    </td>
                                    <td class="is-numeric cell-money cell-nowrap">{{ format_money($invoice->amount) }}</td>
                                    <td class="is-numeric cell-money cell-nowrap">{{ format_money($invoice->paid_total) }}</td>
                                    <td class="is-numeric cell-money cell-nowrap" style="{{ $invoice->balance() > 0 ? 'color: var(--color-danger);' : 'color: var(--color-text-secondary); font-weight: 400;' }}">{{ format_money($invoice->balance()) }}</td>
                                    <td class="cell-muted cell-nowrap">{{ format_date($invoice->due_date) }}</td>
                                    <td><x-status-badge :status="$invoice->effectiveStatus()" /></td>
                                    <td class="is-numeric">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost btn-icon" title="Ver factura" aria-label="Ver factura">{{ icon('eye', 'icon', 16) }}</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card-surface h-100">
                <h2 class="text-h3" style="margin-bottom: var(--space-3);">Historial de pagos</h2>
                @if ($payments->isEmpty())
                    <x-empty-state icon="wallet" title="Sin pagos" message="No hay pagos registrados en el período seleccionado." />
                @else
                    <div class="table-wrap">
                        <table class="data-table data-table-compact">
                            <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cuota</th>
                                <th class="is-numeric">Monto</th>
                                <th>Método</th>
                                <th>Registrado por</th>
                                <th>Estado</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($payments as $payment)
                                <tr class="{{ $payment->isVoided() ? 'row-voided' : '' }}">
                                    <td class="cell-nowrap">{{ format_date($payment->paid_at) }}</td>
                                    <td class="cell-nowrap"><a href="{{ route('invoices.show', $payment->invoice) }}" class="link-plain">{{ format_period($payment->invoice->period) }}</a></td>
                                    <td class="is-numeric cell-money cell-nowrap">{{ format_money($payment->amount) }}</td>
                                    <td class="cell-nowrap"><span class="badge badge-info">{{ $payment->methodLabel() }}</span></td>
                                    <td class="cell-muted cell-nowrap">{{ $payment->registeredBy->name ?? '-' }}</td>
                                    <td>
                                        @if ($payment->isVoided())
                                            <span class="badge badge-neutral" title="{{ $payment->void_reason }}">Anulado</span>
                                        @else
                                            <span class="badge badge-success">Válido</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
