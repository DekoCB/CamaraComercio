@php
    $val = fn (string $field, $default = '') => old($field, isset($benefit) ? $benefit->{$field} : $default);
@endphp

<form method="POST" action="{{ isset($benefit) ? route('benefits.update', $benefit) : route('benefits.store') }}" novalidate>
    @csrf
    @if (isset($benefit))
        @method('PUT')
    @endif

    <div class="field">
        <label class="field-label" for="name">Nombre <span class="required">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required maxlength="150" value="{{ $val('name') }}" placeholder="Uso gratuito del auditorio">
        @error('name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label class="field-label" for="description">Descripción</label>
        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="2" maxlength="1000">{{ $val('description') }}</textarea>
        @error('description')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label class="field-label" for="annual_quota">Cupo anual <span class="required">*</span></label>
        <input type="number" min="1" max="999" step="1" class="form-control @error('annual_quota') is-invalid @enderror" id="annual_quota" name="annual_quota" required value="{{ $val('annual_quota') }}">
        <div class="field-help">Cuántas veces al año puede usar este beneficio cada asociado.</div>
        @error('annual_quota')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
    </div>

    @if (isset($benefit))
        <div class="field">
            <label class="form-check">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" {{ old('is_active', $benefit->is_active) ? 'checked' : '' }}>
                Activo (visible en el conteo por asociado)
            </label>
        </div>
    @endif

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} {{ isset($benefit) ? 'Guardar cambios' : 'Crear beneficio' }}</span>
        </button>
        <a href="{{ route('benefits.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
