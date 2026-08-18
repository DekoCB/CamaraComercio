<form method="POST" action="{{ $action }}" novalidate>
    @csrf

    <div class="field">
        <label class="field-label" for="code">Código <span class="required">*</span></label>
        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" required pattern="[a-z0-9_\-]+"
               placeholder="p. ej. reports" value="{{ old('code') }}">
        <div class="field-help">Solo minúsculas, números, guiones y guiones bajos.</div>
        @error('code')
            <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
        @enderror
    </div>
    <div class="field">
        <label class="field-label" for="name">Nombre <span class="required">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required value="{{ old('name') }}">
        @error('name')
            <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
        @enderror
    </div>
    <div class="field">
        <label class="field-label" for="icon">Ícono (nombre de Lucide)</label>
        <input type="text" class="form-control" id="icon" name="icon" placeholder="bar-chart-3" value="{{ old('icon') }}">
        <div class="field-help">Nombre del ícono en <a href="https://lucide.dev/icons" target="_blank" rel="noopener">lucide.dev/icons</a>, en minúsculas y con guiones.</div>
    </div>
    <div class="field">
        <label class="field-label" for="sort_order">Orden</label>
        <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}">
    </div>
    <div class="form-check field">
        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
        <label for="is_active" style="font-size: 0.875rem;">Módulo activo</label>
    </div>

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('save', 'icon', 16) }} Guardar</span>
        </button>
        <a href="{{ route('admin.modules.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
