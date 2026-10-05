@php
    $val = fn (string $field, $default = '') => old($field, isset($item) ? $item->{$field} : $default);
@endphp

<form method="POST" action="{{ isset($item) ? route('rental-catalog-items.update', $item) : route('rental-catalog-items.store') }}" novalidate>
    @csrf
    @if (isset($item))
        @method('PUT')
    @endif

    <div class="field">
        <label class="field-label" for="name">Nombre <span class="required">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required maxlength="150" value="{{ $val('name') }}">
        @error('name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label class="field-label" for="default_hourly_rate">Tarifa por hora</label>
        <div class="input-money">
            <span class="currency-prefix">S/</span>
            <input type="number" step="0.01" min="0" class="form-control @error('default_hourly_rate') is-invalid @enderror" id="default_hourly_rate" name="default_hourly_rate" value="{{ $val('default_hourly_rate') }}">
        </div>
        <div class="field-help">Déjalo vacío si el ítem se incluye sin costo aparte al cotizar.</div>
        @error('default_hourly_rate')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
    </div>

    @if (isset($item))
        <div class="field">
            <label class="form-check">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                Activo (disponible al cotizar)
            </label>
        </div>
    @endif

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} {{ isset($item) ? 'Guardar cambios' : 'Crear ítem' }}</span>
        </button>
        <a href="{{ route('rental-catalog-items.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
