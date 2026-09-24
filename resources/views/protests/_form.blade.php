<form method="POST" action="{{ route('protests.store') }}" novalidate>
    @csrf

    <div class="row g-3">
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="type">Tipo <span class="required">*</span></label>
                <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                    <option value="">— Selecciona —</option>
                    @foreach (\App\Models\Protest::TYPES as $key => $label)
                        <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="channel">Vía <span class="required">*</span></label>
                <select class="form-select @error('channel') is-invalid @enderror" id="channel" name="channel" required>
                    <option value="">— Selecciona —</option>
                    @foreach (\App\Models\Protest::CHANNELS as $key => $label)
                        <option value="{{ $key }}" {{ old('channel') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('channel')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="instrument_type">Tipo de título</label>
                <select class="form-select @error('instrument_type') is-invalid @enderror" id="instrument_type" name="instrument_type">
                    <option value="">— Selecciona —</option>
                    @foreach (\App\Models\Protest::INSTRUMENT_TYPES as $key => $label)
                        <option value="{{ $key }}" {{ old('instrument_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('instrument_type')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="registered_at">Fecha de registro <span class="required">*</span></label>
                <input type="date" class="form-control @error('registered_at') is-invalid @enderror" id="registered_at" name="registered_at" required
                       value="{{ old('registered_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                @error('registered_at')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>

        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="debtor_name">Deudor <span class="required">*</span></label>
                <input type="text" class="form-control @error('debtor_name') is-invalid @enderror" id="debtor_name" name="debtor_name" required value="{{ old('debtor_name') }}">
                @error('debtor_name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="debtor_document">Documento del deudor</label>
                <input type="text" class="form-control" id="debtor_document" name="debtor_document" maxlength="20" value="{{ old('debtor_document') }}">
            </div>
        </div>

        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="creditor_name">Acreedor <span class="required">*</span></label>
                <input type="text" class="form-control @error('creditor_name') is-invalid @enderror" id="creditor_name" name="creditor_name" required value="{{ old('creditor_name') }}">
                @error('creditor_name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="creditor_document">Documento del acreedor</label>
                <input type="text" class="form-control" id="creditor_document" name="creditor_document" maxlength="20" value="{{ old('creditor_document') }}">
            </div>
        </div>

        <div class="col-md-8">
            <div class="field">
                <label class="field-label" for="associate_id">Asociado que solicita (si aplica)</label>
                <select class="form-select @error('associate_id') is-invalid @enderror" id="associate_id" name="associate_id">
                    <option value="">— Sin asociado (cliente no socio) —</option>
                    @foreach ($associates as $associate)
                        <option value="{{ $associate->id }}" {{ (string) old('associate_id') === (string) $associate->id ? 'selected' : '' }}>{{ $associate->name }}</option>
                    @endforeach
                </select>
                <div class="field-help">Solo si el acreedor es un asociado de la Cámara — habilita el descuento por socio.</div>
                @error('associate_id')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-4">
            <div class="field">
                <label class="field-label" for="amount">Monto cobrado <span class="required">*</span></label>
                <div class="input-money">
                    <span class="currency-prefix">S/</span>
                    <input type="number" step="0.01" min="0" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" required value="{{ old('amount') }}">
                </div>
                @error('amount')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>

        <div class="col-12">
            <div class="field">
                <label class="field-label" for="notes">Notas</label>
                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} Registrar</span>
        </button>
        <a href="{{ route('protests.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
