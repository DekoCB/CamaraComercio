@extends('layouts.app')

@section('title', 'Factura — '.$invoice->associate->name.' ('.$invoice->period.')')

@php
    $associate = $invoice->associate;
    $balance = $invoice->balance();
    $status = $invoice->effectiveStatus();
    $percent = (float) $invoice->amount > 0 ? min(100, round((float) $invoice->paid_total / (float) $invoice->amount * 100)) : 0;
    $today = \Carbon\CarbonImmutable::today();
    $due = \Carbon\CarbonImmutable::instance($invoice->due_date)->startOfDay();
    $activePayments = $invoice->payments->whereNull('voided_at')->sortByDesc('paid_at');
    $lastPayment = $activePayments->first();

    if ($balance <= 0) {
        $dueNote = ['text' => 'Pagada'.($lastPayment ? ' el '.format_date($lastPayment->paid_at) : ''), 'class' => 'is-success', 'icon' => 'check-circle-2'];
    } elseif ($due->lessThan($today)) {
        $dueNote = ['text' => 'Vencida hace '.$due->diffInDays($today).' día(s)', 'class' => 'is-danger', 'icon' => 'alert-triangle'];
    } elseif ($due->equalTo($today)) {
        $dueNote = ['text' => 'Vence hoy', 'class' => 'is-warning', 'icon' => 'clock'];
    } else {
        $dueNote = ['text' => 'Vence en '.$today->diffInDays($due).' día(s)', 'class' => 'is-info', 'icon' => 'clock'];
    }
@endphp

@section('content')
    <x-page-header :title="'Factura '.($invoice->receipt_number ?? '#'.$invoice->id)" :subtitle="$associate->name.' · '.format_period($invoice->period)">
        <x-slot:actions>
            <a href="{{ route('invoices.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
            <a href="{{ route('associates.show', $associate) }}" class="btn btn-secondary btn-sm">
                {{ icon('users', 'icon', 16) }} Ficha del asociado
            </a>
            @can('portfolio.view')
                <a href="{{ route('associates.statement', $associate) }}" class="btn btn-secondary btn-sm">
                    {{ icon('receipt', 'icon', 16) }} Estado de cuenta
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            {{-- Resumen de la factura --}}
            <div class="card-surface invoice-hero mb-3">
                <div class="invoice-hero-top">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <x-status-badge :status="$status" />
                            <span class="due-note {{ $dueNote['class'] }}">{{ icon($dueNote['icon'], 'icon', 13) }} {{ $dueNote['text'] }}</span>
                        </div>
                        <h2 class="invoice-hero-title">
                            <a href="{{ route('associates.show', $associate) }}" class="link-plain">{{ $associate->name }}</a>
                        </h2>
                        <div class="invoice-hero-meta">
                            @if ($associate->company && $associate->company !== $associate->name)
                                <span>{{ $associate->company }}</span>
                            @endif
                            @if ($associate->ruc)
                                <span>RUC {{ $associate->ruc }}</span>
                            @endif
                            @if ($associate->sectorista)
                                <span class="badge badge-neutral">Sectorista: {{ $associate->sectorista }}</span>
                            @endif
                            @if ($associate->category)
                                <span class="badge badge-neutral">Cat. {{ $associate->category }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="invoice-nav">
                        @if ($previousInvoice)
                            <a href="{{ route('invoices.show', $previousInvoice) }}" class="btn btn-ghost btn-sm" title="Cuota anterior del asociado">
                                {{ icon('chevron-left', 'icon', 15) }} {{ format_period($previousInvoice->period) }}
                            </a>
                        @endif
                        @if ($nextInvoice)
                            <a href="{{ route('invoices.show', $nextInvoice) }}" class="btn btn-ghost btn-sm" title="Cuota siguiente del asociado">
                                {{ format_period($nextInvoice->period) }} {{ icon('chevron-right', 'icon', 15) }}
                            </a>
                        @endif
                    </div>
                </div>

                <div class="invoice-amounts">
                    <div class="invoice-amount">
                        <div class="text-label">Monto de la cuota</div>
                        <div class="invoice-amount-value">{{ format_money($invoice->amount) }}</div>
                    </div>
                    <div class="invoice-amount">
                        <div class="text-label">Pagado</div>
                        <div class="invoice-amount-value" style="color: var(--color-success);">{{ format_money($invoice->paid_total) }}</div>
                    </div>
                    <div class="invoice-amount">
                        <div class="text-label">Saldo pendiente</div>
                        <div class="invoice-amount-value" style="{{ $balance > 0 ? 'color: var(--color-danger);' : 'color: var(--color-text-secondary);' }}">{{ format_money($balance) }}</div>
                    </div>
                </div>

                <div class="pay-progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Porcentaje pagado">
                    <div class="pay-progress-bar {{ $percent >= 100 ? 'is-complete' : '' }}" style="width: {{ $percent }}%"></div>
                </div>
                <div class="pay-progress-label">
                    <span>{{ $percent }} % pagado</span>
                    <span>{{ $activePayments->count() }} pago{{ $activePayments->count() === 1 ? '' : 's' }} válido{{ $activePayments->count() === 1 ? '' : 's' }}</span>
                </div>

                <dl class="detail-grid invoice-detail-grid">
                    <div class="detail-item"><dt>Período</dt><dd>{{ format_period($invoice->period) }} <span class="cell-muted">({{ $invoice->period }})</span></dd></div>
                    <div class="detail-item"><dt>N° de comprobante</dt><dd>{{ $invoice->receipt_number ?? '—' }}</dd></div>
                    <div class="detail-item"><dt>Fecha de emisión</dt><dd>{{ format_date($invoice->issue_date) }}</dd></div>
                    <div class="detail-item"><dt>Fecha de vencimiento</dt><dd>{{ format_date($invoice->due_date) }}</dd></div>
                    <div class="detail-item"><dt>Último pago</dt><dd>{{ $lastPayment ? format_date($lastPayment->paid_at).' · '.$lastPayment->methodLabel() : '—' }}</dd></div>
                    <div class="detail-item"><dt>Generada por</dt><dd>{{ $invoice->creator->name ?? 'Importación / sistema' }} <span class="cell-muted">{{ $invoice->created_at->format('d/m/Y') }}</span></dd></div>
                </dl>
            </div>

            {{-- Historial de pagos --}}
            <div class="card-surface mb-3">
                <div class="d-flex justify-content-between align-items-center" style="margin-bottom: var(--space-3);">
                    <h2 class="text-h3" style="margin: 0;">Historial de pagos</h2>
                    @if ($invoice->payments->whereNotNull('voided_at')->isNotEmpty())
                        <span class="cell-muted" style="font-size: var(--text-xs);">{{ $invoice->payments->whereNotNull('voided_at')->count() }} anulado(s) se muestran tachados</span>
                    @endif
                </div>
                @if ($invoice->payments->isEmpty())
                    <x-empty-state icon="wallet" title="Sin pagos registrados" message="Todavía no se registran pagos para esta factura." />
                @else
                    <div class="table-wrap">
                        <table class="data-table data-table-compact">
                            <thead>
                            <tr>
                                <th>Fecha</th>
                                <th class="is-numeric">Monto</th>
                                <th>Método</th>
                                <th>Registrado por</th>
                                <th>Notas</th>
                                <th>Estado</th>
                                @can('payments.void')
                                    <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                                @endcan
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($invoice->payments->sortByDesc('paid_at') as $payment)
                                <tr class="{{ $payment->isVoided() ? 'row-voided' : '' }}">
                                    <td class="cell-nowrap">
                                        {{ format_date($payment->paid_at) }}
                                        <div class="cell-muted" style="font-size: var(--text-xs);">{{ $payment->paid_at->format('H:i') }}</div>
                                    </td>
                                    <td class="is-numeric cell-money cell-nowrap">{{ format_money($payment->amount) }}</td>
                                    <td class="cell-nowrap"><span class="badge badge-info">{{ $payment->methodLabel() }}</span></td>
                                    <td class="cell-muted">{{ $payment->registeredBy->name ?? '-' }}</td>
                                    <td class="cell-muted cell-clamp">{{ $payment->notes ?? '-' }}</td>
                                    <td>
                                        @if ($payment->isVoided())
                                            <span class="badge badge-neutral">Anulado</span>
                                            <div class="cell-muted" style="font-size: var(--text-xs); margin-top: 2px;">
                                                {{ $payment->void_reason }}
                                                @if ($payment->voidedBy)
                                                    · {{ $payment->voidedBy->name }}, {{ format_date($payment->voided_at) }}
                                                @endif
                                            </div>
                                        @else
                                            <span class="badge badge-success">Válido</span>
                                        @endif
                                    </td>
                                    @can('payments.void')
                                        <td class="is-numeric">
                                            @if (! $payment->isVoided())
                                                <details class="void-payment-details">
                                                    <summary class="btn btn-ghost btn-ghost-danger btn-sm" style="cursor: pointer; display: inline-flex;">
                                                        {{ icon('x-circle', 'icon', 15) }} Anular
                                                    </summary>
                                                    <form method="POST" action="{{ route('payments.void', $payment) }}" class="mt-2" style="min-width: 220px;"
                                                          data-confirm="¿Anular este pago? Esta acción no se puede deshacer." data-confirm-title="Anular pago">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="text" class="form-control" name="reason" maxlength="255" required placeholder="Motivo de la anulación">
                                                        <button type="submit" class="btn btn-danger btn-sm mt-2">Confirmar anulación</button>
                                                    </form>
                                                </details>
                                            @endif
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Otras cuotas del asociado --}}
            <div class="card-surface">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2 class="text-h3" style="margin: 0 0 4px;">Otras cuotas de {{ $associate->company ?: $associate->name }}</h2>
                        <div class="cell-muted" style="font-size: 0.875rem;">
                            {{ $associateSummary['total'] }} cuota{{ $associateSummary['total'] === 1 ? '' : 's' }} en total ·
                            @if ($associateSummary['pending_count'] > 0)
                                <strong style="color: var(--color-danger);">{{ $associateSummary['pending_count'] }} con saldo por {{ format_money($associateSummary['pending_balance']) }}</strong>
                                @if ($associateSummary['overdue_count'] > 0)
                                    ({{ $associateSummary['overdue_count'] }} vencida{{ $associateSummary['overdue_count'] === 1 ? '' : 's' }})
                                @endif
                            @else
                                <span style="color: var(--color-success);">sin otras deudas pendientes</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('invoices.index', ['associate_id' => $associate->id]) }}" class="btn btn-secondary btn-sm">{{ icon('file-text', 'icon', 15) }} Todas sus facturas</a>
                        @if ($associateSummary['pending_count'] > 0)
                            <a href="{{ route('invoices.index', ['associate_id' => $associate->id, 'status' => 'no_pagadas']) }}" class="btn btn-secondary btn-sm">{{ icon('alert-triangle', 'icon', 15) }} Solo pendientes</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="invoice-sidebar">
                @can('payments.register')
                    <div class="card-surface mb-3">
                        <h2 class="text-h3" style="margin-bottom: var(--space-4);">Registrar pago</h2>
                        @if ($balance <= 0)
                            <div class="paid-panel">
                                {{ icon('check-circle-2', 'icon', 28) }}
                                <div>
                                    <strong>Factura pagada en su totalidad</strong>
                                    <div class="cell-muted" style="font-size: var(--text-xs);">No queda saldo por cobrar en esta cuota.</div>
                                </div>
                            </div>
                        @else
                            <div class="pay-preview">
                                <div>
                                    <div class="text-label">Saldo actual</div>
                                    <div class="fw-semibold">{{ format_money($balance) }}</div>
                                </div>
                                <div class="pay-preview-arrow">{{ icon('arrow-down-right', 'icon', 16) }}</div>
                                <div>
                                    <div class="text-label">Nuevo saldo</div>
                                    <div class="fw-semibold" id="newBalancePreview" style="color: var(--color-danger);">{{ format_money($balance) }}</div>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('payments.store', $invoice) }}" novalidate>
                                @csrf
                                <div class="field">
                                    <label class="field-label" for="amount">Monto a pagar <span class="required">*</span></label>
                                    <div class="input-money">
                                        <span class="currency-prefix">S/</span>
                                        <input type="number" step="0.01" min="0.01" max="{{ $balance }}"
                                               class="form-control @error('amount') is-invalid @enderror"
                                               id="amount" name="amount" required value="{{ old('amount') }}"
                                               data-invoice-amount="{{ $invoice->amount }}" data-invoice-paid="{{ $invoice->paid_total }}">
                                    </div>
                                    <div class="quick-amounts">
                                        <button type="button" class="btn btn-ghost btn-sm js-quick-amount" data-amount="{{ $balance }}">Pago total ({{ format_money($balance) }})</button>
                                        <button type="button" class="btn btn-ghost btn-sm js-quick-amount" data-amount="{{ round($balance / 2, 2) }}">Mitad</button>
                                    </div>
                                    @error('amount')
                                        <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="field">
                                            <label class="field-label" for="paid_at">Fecha de pago <span class="required">*</span></label>
                                            <input type="date" class="form-control @error('paid_at') is-invalid @enderror" id="paid_at" name="paid_at" required
                                                   value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="field">
                                            <label class="field-label" for="method">Método <span class="required">*</span></label>
                                            <select class="form-select @error('method') is-invalid @enderror" id="method" name="method" required>
                                                @foreach (\App\Models\Payment::METHODS as $key => $label)
                                                    <option value="{{ $key }}" {{ old('method', 'EFECTIVO') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('method')
                                                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label" for="notes">Notas</label>
                                    <input type="text" class="form-control" id="notes" name="notes" maxlength="255" value="{{ old('notes') }}" placeholder="N° de operación, observaciones…">
                                </div>
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    <span class="spinner"></span>
                                    <span class="btn-label-idle">{{ icon('wallet', 'icon', 16) }} Registrar pago</span>
                                </button>
                            </form>
                        @endif
                    </div>
                @endcan

                {{-- Contacto para la cobranza --}}
                <div class="card-surface">
                    <h2 class="text-h3" style="margin-bottom: var(--space-3);">Contacto para cobranza</h2>
                    <dl class="detail-grid" style="grid-template-columns: 1fr;">
                        <div class="detail-item"><dt>Empresa</dt><dd>
                            @if ($associate->contact_phone) <div>{{ icon('phone', 'icon', 13) }} {{ $associate->contact_phone }}</div> @endif
                            @if ($associate->email) <div>{{ icon('mail', 'icon', 13) }} {{ $associate->email }}</div> @endif
                            @if (! $associate->contact_phone && ! $associate->email) — @endif
                        </dd></div>
                        <div class="detail-item"><dt>Representante legal</dt><dd>
                            {{ $associate->legal_rep_name ?? '—' }}
                            @if ($associate->legal_rep_phone) <div class="cell-muted">{{ icon('phone', 'icon', 13) }} {{ $associate->legal_rep_phone }}</div> @endif
                            @if ($associate->legal_rep_email) <div class="cell-muted">{{ icon('mail', 'icon', 13) }} {{ $associate->legal_rep_email }}</div> @endif
                        </dd></div>
                        @if ($associate->cch_rep_name && $associate->cch_rep_name !== $associate->legal_rep_name)
                            <div class="detail-item"><dt>Representante ante la CCH</dt><dd>
                                {{ $associate->cch_rep_name }}
                                @if ($associate->cch_rep_phone) <div class="cell-muted">{{ icon('phone', 'icon', 13) }} {{ $associate->cch_rep_phone }}</div> @endif
                            </dd></div>
                        @endif
                        @if ($associate->billing_address)
                            <div class="detail-item"><dt>Dirección de facturación</dt><dd>{{ $associate->billing_address }}{{ $associate->billing_district ? ', '.$associate->billing_district : '' }}</dd></div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var input = document.getElementById('amount');
    var preview = document.getElementById('newBalancePreview');
    if (!input || !preview) { return; }

    var amount = parseFloat(input.dataset.invoiceAmount);
    var paid = parseFloat(input.dataset.invoicePaid);
    var balance = amount - paid;

    function formatMoney(value) {
        return 'S/ ' + value.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function refresh() {
        var entered = parseFloat(input.value);
        if (isNaN(entered) || entered < 0) {
            preview.textContent = formatMoney(balance);
            preview.style.color = 'var(--color-danger)';
            return;
        }
        preview.textContent = formatMoney(balance - entered);
        preview.style.color = (balance - entered) > 0.004 ? 'var(--color-danger)' : 'var(--color-success)';
    }

    input.addEventListener('input', refresh);
    document.querySelectorAll('.js-quick-amount').forEach(function (button) {
        button.addEventListener('click', function () {
            input.value = button.dataset.amount;
            refresh();
            input.focus();
        });
    });
})();
</script>
@endpush
