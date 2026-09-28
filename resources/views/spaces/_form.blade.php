@php
    $val = fn (string $field, $default = '') => old($field, isset($space) ? $space->{$field} : $default);
@endphp

<form method="POST" action="{{ isset($space) ? route('spaces.update', $space) : route('spaces.store') }}" novalidate>
    @csrf
    @if (isset($space))
        @method('PUT')
    @endif

    <div class="field">
        <label class="field-label" for="name">Nombre <span class="required">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required maxlength="255" value="{{ $val('name') }}">
        @error('name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label class="field-label" for="description">Descripción</label>
        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="2" maxlength="1000">{{ $val('description') }}</textarea>
        @error('description')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label class="field-label" for="default_rate">Tarifa referencial</label>
        <div class="input-money">
            <span class="currency-prefix">S/</span>
            <input type="number" step="0.01" min="0" class="form-control @error('default_rate') is-invalid @enderror" id="default_rate" name="default_rate" value="{{ $val('default_rate') }}">
        </div>
        <div class="field-help">Solo referencial — el monto real se ajusta en cada cotización.</div>
        @error('default_rate')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
    </div>

    @if (isset($space))
        <div class="field">
            <label class="form-check">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" {{ old('is_active', $space->is_active) ? 'checked' : '' }}>
                Activo (visible para nuevas cotizaciones)
            </label>
        </div>
    @endif

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} {{ isset($space) ? 'Guardar cambios' : 'Crear espacio' }}</span>
        </button>
        <a href="{{ route('spaces.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
