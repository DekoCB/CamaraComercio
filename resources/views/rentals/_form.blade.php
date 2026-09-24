@php
    $val = fn (string $field, $default = '') => old($field, isset($rental) ? $rental->{$field} : $default);
@endphp

<form method="POST" action="{{ isset($rental) ? route('rentals.update', $rental) : route('rentals.store') }}" novalidate>
    @csrf
    @if (isset($rental))
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="space_id">Espacio <span class="required">*</span></label>
                <select class="form-select @error('space_id') is-invalid @enderror" id="space_id" name="space_id" required>
                    <option value="">— Selecciona —</option>
                    @foreach ($spaces as $space)
                        <option value="{{ $space->id }}" {{ (string) $val('space_id') === (string) $space->id ? 'selected' : '' }}>{{ $space->name }}</option>
                    @endforeach
                </select>
                @error('space_id')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="associate_id">Asociado <span class="required">*</span></label>
                <select class="form-select @error('associate_id') is-invalid @enderror" id="associate_id" name="associate_id" required>
                    <option value="">— Selecciona —</option>
                    @foreach ($associates as $associate)
                        <option value="{{ $associate->id }}" {{ (string) $val('associate_id') === (string) $associate->id ? 'selected' : '' }}>{{ $associate->name }}</option>
                    @endforeach
                </select>
                @error('associate_id')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="starts_at">Inicio <span class="required">*</span></label>
                <input type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" name="starts_at" required
                       value="{{ old('starts_at', isset($rental) ? $rental->starts_at->format('Y-m-d\TH:i') : '') }}">
                @error('starts_at')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="ends_at">Fin <span class="required">*</span></label>
                <input type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" name="ends_at" required
                       value="{{ old('ends_at', isset($rental) ? $rental->ends_at->format('Y-m-d\TH:i') : '') }}">
                @error('ends_at')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="amount">Monto <span class="required">*</span></label>
                <div class="input-money">
                    <span class="currency-prefix">S/</span>
                    <input type="number" step="0.01" min="0" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" required value="{{ $val('amount') }}">
                </div>
                @error('amount')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="purpose">Motivo</label>
                <input type="text" class="form-control @error('purpose') is-invalid @enderror" id="purpose" name="purpose" maxlength="255" value="{{ $val('purpose') }}" placeholder="Charla, capacitación, evento...">
                @error('purpose')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-12">
            <div class="field">
                <label class="field-label" for="notes">Notas</label>
                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" maxlength="1000">{{ $val('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} {{ isset($rental) ? 'Guardar cambios' : 'Crear cotización' }}</span>
        </button>
        <a href="{{ isset($rental) ? route('rentals.show', $rental) : route('rentals.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
