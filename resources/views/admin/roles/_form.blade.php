<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="field">
        <label class="field-label" for="name">Nombre <span class="required">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required
               value="{{ old('name', $role->name ?? '') }}">
        @error('name')
            <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
        @enderror
    </div>
    <div class="field">
        <label class="field-label" for="description">Descripción</label>
        <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $role->description ?? '') }}</textarea>
    </div>

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('save', 'icon', 16) }} Guardar</span>
        </button>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
