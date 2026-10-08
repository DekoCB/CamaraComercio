<form method="POST" action="{{ route('plates.store') }}" class="js-plate-form" novalidate>
    @csrf

    <div class="row g-3">
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="procedure_type">Tipo de trámite <span class="required">*</span></label>
                <select class="form-select @error('procedure_type') is-invalid @enderror" id="procedure_type" name="procedure_type" required>
                    <option value="">— Selecciona —</option>
                    @foreach (\App\Models\PlateIssuance::PROCEDURE_TYPES as $key => $label)
                        <option value="{{ $key }}" data-rate="{{ $rates[$key] ?? '' }}" {{ old('procedure_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('procedure_type')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="other_description">Descripción (si el trámite es "Otros")</label>
                <input type="text" class="form-control @error('other_description') is-invalid @enderror" id="other_description" name="other_description" maxlength="150" value="{{ old('other_description') }}">
                @error('other_description')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>

        <div class="col-md-4">
            <div class="field">
                <label class="field-label" for="plate_number">Placa</label>
                <input type="text" class="form-control @error('plate_number') is-invalid @enderror" id="plate_number" name="plate_number" maxlength="10" style="text-transform: uppercase;" value="{{ old('plate_number') }}">
                @error('plate_number')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-4">
            <div class="field">
                <label class="field-label" for="issued_at">Fecha del trámite <span class="required">*</span></label>
                <input type="date" class="form-control @error('issued_at') is-invalid @enderror" id="issued_at" name="issued_at" required
                       value="{{ old('issued_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                @error('issued_at')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-4">
            <div class="field">
                <label class="field-label" for="vehicle_description">Vehículo</label>
                <input type="text" class="form-control" id="vehicle_description" name="vehicle_description" maxlength="150" placeholder="Marca, modelo..." value="{{ old('vehicle_description') }}">
            </div>
        </div>

        <div class="col-md-8">
            <div class="field">
                <label class="field-label" for="associate_id">Asociado solicitante (si aplica)</label>
                <select class="form-select @error('associate_id') is-invalid @enderror" id="associate_id" name="associate_id">
                    <option value="">— Sin asociado —</option>
                    @foreach ($associates as $associate)
                        <option value="{{ $associate->id }}" {{ (string) old('associate_id') === (string) $associate->id ? 'selected' : '' }}>{{ $associate->name }}</option>
                    @endforeach
                </select>
                @error('associate_id')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-4">
            <div class="field">
                <label class="field-label" for="client_name">Nombre del solicitante</label>
                <input type="text" class="form-control @error('client_name') is-invalid @enderror" id="client_name" name="client_name" maxlength="150" value="{{ old('client_name') }}">
                <div class="field-help">Obligatorio solo si no es un asociado.</div>
                @error('client_name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>

        <div class="col-md-4">
            <div class="field">
                <label class="field-label" for="receipt_type">Comprobante <span class="required">*</span></label>
                <select class="form-select @error('receipt_type') is-invalid @enderror" id="receipt_type" name="receipt_type" required>
                    <option value="">— Selecciona —</option>
                    @foreach (\App\Models\PlateIssuance::RECEIPT_TYPES as $key => $label)
                        <option value="{{ $key }}" {{ old('receipt_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('receipt_type')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-4">
            <div class="field">
                <label class="field-label" for="receipt_number">N° de comprobante</label>
                <input type="text" class="form-control" id="receipt_number" name="receipt_number" maxlength="30" value="{{ old('receipt_number') }}">
            </div>
        </div>
        <div class="col-md-4">
            <div class="field">
                <label class="field-label" for="amount">Costo <span class="required">*</span></label>
                <div class="input-money">
                    <span class="currency-prefix">S/</span>
                    <input type="number" step="0.01" min="0" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" required value="{{ old('amount') }}">
                </div>
                <div class="field-help" id="suggestedRateHint" hidden>
                    Tarifa sugerida: <strong id="suggestedRateValue"></strong>
                    <button type="button" class="btn btn-link btn-sm" id="useSuggestedRate" style="padding: 0;">Usar</button>
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
        <a href="{{ route('plates.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
