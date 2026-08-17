@extends('layouts.app')

@section('title', $user ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
    <div class="table-card" style="max-width: 640px">
        <form method="POST" action="{{ $action }}" novalidate>
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="mb-3">
                <label class="form-label" for="name">Nombre *</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required
                       value="{{ old('name', $user->name ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Correo *</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" required
                       value="{{ old('email', $user->email ?? '') }}">
            </div>
            @unless ($user)
                <div class="mb-3">
                    <label class="form-label" for="password">Contraseña *</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" minlength="8" required>
                    <div class="form-text">Mínimo 8 caracteres.</div>
                </div>
            @endunless
            <div class="mb-3">
                <label class="form-label" for="role_id">Rol *</label>
                <select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id" required>
                    <option value="">Seleccione…</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ old('role_id', $user->role_id ?? '') == $role->id ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                       {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Usuario activo</label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
