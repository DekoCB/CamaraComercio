@if ($invoices->isEmpty())
    <p class="text-secondary" style="font-size: 0.875rem;">No hay facturas con saldo pendiente en este momento.</p>
    <a href="{{ route('payments.index') }}" class="btn btn-secondary js-modal-cancel">Cerrar</a>
@else
    <form method="POST" action="{{ route('payments.storeQuick') }}" novalidate>
        @csrf
        <div class="field">
            <label class="field-label" for="invoice_id">Factura <span class="required">*</span></label>
            <select class="form-select @error('invoice_id') is-invalid @enderror" id="invoice_id" name="invoice_id" required>
                <option value="">Selecciona una factura...</option>
                @foreach ($invoices as $invoice)
                    <option value="{{ $invoice->id }}" {{ (string) old('invoice_id') === (string) $invoice->id ? 'selected' : '' }}>
                        {{ $invoice->associate->name }} — {{ $invoice->period }} — Saldo: {{ format_money($invoice->balance()) }}
                    </option>
                @endforeach
            </select>
            @error('invoice_id')
                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
            @enderror
        </div>
        <div class="field">
            <label class="field-label" for="amount">Monto a pagar <span class="required">*</span></label>
            <div class="input-money">
                <span class="currency-prefix">S/</span>
                <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror"
                       id="amount" name="amount" required value="{{ old('amount') }}">
            </div>
            @error('amount')
                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
            @enderror
        </div>
        <div class="field">
            <label class="field-label" for="paid_at">Fecha de pago <span class="required">*</span></label>
            <input type="date" class="form-control @error('paid_at') is-invalid @enderror" id="paid_at" name="paid_at" required
                   value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
            @error('paid_at')
                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
            @enderror
        </div>
        <div class="field">
            <label class="field-label" for="notes">Notas</label>
            <input type="text" class="form-control" id="notes" name="notes" maxlength="255" value="{{ old('notes') }}">
        </div>

        <div class="d-flex gap-2" style="margin-top: var(--space-6);">
            <button type="submit" class="btn btn-primary">
                <span class="spinner"></span>
                <span class="btn-label-idle">{{ icon('wallet', 'icon', 16) }} Registrar pago</span>
            </button>
            <a href="{{ route('payments.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
        </div>
    </form>
@endif
