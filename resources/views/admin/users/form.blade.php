@extends('layouts.app')

@section('title', $user ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
    <x-page-header :title="$user ? 'Editar usuario' : 'Nuevo usuario'" />

    <div class="card-surface" style="max-width: 560px">
        <form method="POST" action="{{ $action }}" novalidate>
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="field">
                <label class="field-label" for="name">Nombre <span class="required">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required
                       value="{{ old('name', $user->name ?? '') }}">
                @error('name')
                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
            </div>
            <div class="field">
                <label class="field-label" for="email">Correo <span class="required">*</span></label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" required
                       value="{{ old('email', $user->email ?? '') }}">
                @error('email')
                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
            </div>
            @unless ($user)
                <div class="field">
                    <label class="field-label" for="password">Contraseña <span class="required">*</span></label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" minlength="8" required>
                    <div class="field-help">Mínimo 8 caracteres.</div>
                    @error('password')
                        <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                    @enderror
                </div>
            @endunless
            <div class="field">
                <label class="field-label" for="role_id">Rol <span class="required">*</span></label>
                <select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id" required>
                    <option value="">Seleccione…</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ old('role_id', $user->role_id ?? '') == $role->id ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                @error('role_id')
                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
            </div>
            <div class="form-check field">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
                <label for="is_active" style="font-size: 0.875rem;">Usuario activo</label>
            </div>

            <div class="d-flex gap-2" style="margin-top: var(--space-6);">
                <button type="submit" class="btn btn-primary">
                    <span class="spinner"></span>
                    <span class="btn-label-idle">{{ icon('save', 'icon', 16) }} Guardar</span>
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
