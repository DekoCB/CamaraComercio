<form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" novalidate id="profileForm">
    @csrf
    @method('PUT')

    <div class="field" style="display: flex; align-items: center; gap: var(--space-4);">
        <x-avatar :user="$user" :size="64" />
        <div>
            <label for="avatar" class="btn btn-secondary btn-sm" style="cursor: pointer;">
                {{ icon('upload', 'icon', 14) }} Cambiar foto
            </label>
            <input type="file" id="avatar" name="avatar" accept="image/*" class="visually-hidden">
            @if ($user->avatar_path)
                <label class="d-flex align-items-center gap-1" style="font-size: 0.8rem; margin-top: var(--space-2); font-weight: 400;">
                    <input type="checkbox" class="form-check-input" name="remove_avatar" value="1"> Quitar foto actual
                </label>
            @endif
            <div class="field-help">JPG, PNG o WEBP. Máximo 2 MB.</div>
            @error('avatar')
                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="field">
        <label class="field-label" for="name">Nombre <span class="required">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required
               value="{{ old('name', $user->name) }}">
        @error('name')
            <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
        @enderror
    </div>

    <div class="field">
        <label class="field-label" for="email">Correo <span class="required">*</span></label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" required
               value="{{ old('email', $user->email) }}">
        @error('email')
            <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
        @enderror
    </div>

    <hr>
    <p class="text-secondary" style="font-size: 0.8125rem; margin-bottom: var(--space-4);">
        Cambiar contraseña (opcional) — deja estos campos en blanco para no cambiarla.
    </p>

    <div class="field">
        <label class="field-label" for="current_password">Contraseña actual</label>
        <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" autocomplete="current-password">
        @error('current_password')
            <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
        @enderror
    </div>

    <div class="field">
        <label class="field-label" for="password">Nueva contraseña</label>
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" minlength="8" autocomplete="new-password">
        <div class="field-help">Mínimo 8 caracteres.</div>
        @error('password')
            <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
        @enderror
    </div>

    <div class="field">
        <label class="field-label" for="password_confirmation">Confirmar nueva contraseña</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
    </div>

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('save', 'icon', 16) }} Guardar cambios</span>
        </button>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
