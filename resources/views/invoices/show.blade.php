@extends('layouts.app')

@section('title', 'Factura — '.$invoice->associate->name.' ('.$invoice->period.')')

@section('content')
    <x-page-header title="Detalle de factura" :subtitle="$invoice->associate->name.' · '.$invoice->period" />

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card-surface mb-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="text-h3">{{ $invoice->associate->name }}</h2>
                        <div class="text-secondary" style="font-size: 0.8rem;">{{ $invoice->associate->company }}</div>
                    </div>
                    <x-status-badge :status="$invoice->effectiveStatus()" />
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="text-label">Período</div>
                        <div class="fw-semibold">{{ $invoice->period }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-label">Emisión</div>
                        <div class="fw-semibold">{{ format_date($invoice->issue_date) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-label">Vence</div>
                        <div class="fw-semibold">{{ format_date($invoice->due_date) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-label">Generada por</div>
                        <div class="fw-semibold">{{ $invoice->creator->name ?? '-' }}</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-4">
                        <div class="text-label">Monto</div>
                        <div style="font-size: 1.3rem; font-weight: 700;">{{ format_money($invoice->amount) }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-label">Pagado</div>
                        <div style="font-size: 1.3rem; font-weight: 700; color: var(--color-success);">{{ format_money($invoice->paid_total) }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-label">Saldo</div>
                        <div style="font-size: 1.3rem; font-weight: 700; {{ $invoice->balance() > 0 ? 'color: var(--color-danger);' : '' }}">{{ format_money($invoice->balance()) }}</div>
                    </div>
                </div>
            </div>

            <div class="card-surface">
                <h2 class="text-h3" style="margin-bottom: var(--space-4);">Historial de pagos</h2>
                @if ($invoice->payments->isEmpty())
                    <x-empty-state icon="wallet" title="Sin pagos registrados" message="Todavía no se registran pagos para esta factura." />
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Fecha</th>
                                <th class="is-numeric">Monto</th>
                                <th>Registrado por</th>
                                <th>Notas</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($invoice->payments->sortByDesc('paid_at') as $payment)
                                <tr>
                                    <td class="cell-muted">{{ format_date($payment->paid_at) }}</td>
                                    <td class="is-numeric cell-money">{{ format_money($payment->amount) }}</td>
                                    <td class="cell-muted">{{ $payment->registeredBy->name ?? '-' }}</td>
                                    <td class="cell-muted">{{ $payment->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            @can('payments.register')
                <div class="card-surface">
                    <h2 class="text-h3" style="margin-bottom: var(--space-4);">Registrar pago</h2>
                    @if ($invoice->balance() <= 0)
                        <p class="text-secondary" style="font-size: 0.875rem;">Esta factura ya está pagada en su totalidad.</p>
                    @else
                        <div class="card-surface" style="background: var(--color-bg); border-style: dashed; margin-bottom: var(--space-4);">
                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <div class="text-label">Factura</div>
                                    <div class="fw-semibold">{{ format_money($invoice->amount) }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-label">Pagado</div>
                                    <div class="fw-semibold">{{ format_money($invoice->paid_total) }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-label">Nuevo saldo</div>
                                    <div class="fw-semibold" id="newBalancePreview" style="color: var(--color-danger);">{{ format_money($invoice->balance()) }}</div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('payments.store', $invoice) }}" novalidate>
                            @csrf
                            <div class="field">
                                <label class="field-label" for="amount">Monto a pagar <span class="required">*</span></label>
                                <div class="input-money">
                                    <span class="currency-prefix">S/</span>
                                    <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance() }}"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           id="amount" name="amount" required value="{{ old('amount') }}"
                                           data-invoice-amount="{{ $invoice->amount }}" data-invoice-paid="{{ $invoice->paid_total }}">
                                </div>
                                <div class="field-help">Saldo pendiente: {{ format_money($invoice->balance()) }}</div>
                                @error('amount')
                                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                                @enderror
                            </div>
                            <div class="field">
                                <label class="field-label" for="paid_at">Fecha de pago <span class="required">*</span></label>
                                <input type="date" class="form-control @error('paid_at') is-invalid @enderror" id="paid_at" name="paid_at" required
                                       value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                            </div>
                            <div class="field">
                                <label class="field-label" for="notes">Notas</label>
                                <input type="text" class="form-control" id="notes" name="notes" maxlength="255" value="{{ old('notes') }}">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <span class="spinner"></span>
                                <span class="btn-label-idle">{{ icon('wallet', 'icon', 16) }} Registrar pago</span>
                            </button>
                        </form>
                    @endif
                </div>
            @endcan
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

    input.addEventListener('input', function () {
        var entered = parseFloat(input.value);
        if (isNaN(entered) || entered < 0) {
            preview.textContent = formatMoney(balance);
            return;
        }
        preview.textContent = formatMoney(balance - entered);
        preview.style.color = (balance - entered) > 0.004 ? 'var(--color-danger)' : 'var(--color-success)';
    });
})();
</script>
@endpush
