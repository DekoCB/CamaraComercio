<form method="POST" action="{{ route('parking.checkout', $session) }}" novalidate>
    @csrf
    @method('PUT')

    <p class="cell-muted" style="margin-top:0;">
        <strong class="mono">{{ $session->plate }}</strong> — {{ $session->ownerLabel() }}<br>
        Entró {{ $session->entered_at->format('d/m/Y H:i') }}
    </p>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="exited_at">Hora de salida <span class="required">*</span></label>
                <input type="datetime-local" class="form-control @error('exited_at') is-invalid @enderror" id="exited_at" name="exited_at" required
                       value="{{ old('exited_at', now()->format('Y-m-d\TH:i')) }}">
                @error('exited_at')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="amount">Monto cobrado</label>
                <div class="input-money">
                    <span class="currency-prefix">S/</span>
                    <input type="number" step="0.01" min="0" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount', $session->amount) }}">
                </div>
                @error('amount')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('log-out', 'icon', 16) }} Registrar salida</span>
        </button>
        <a href="{{ route('parking.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
