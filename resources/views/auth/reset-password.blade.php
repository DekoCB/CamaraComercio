@extends('layouts.guest')

@section('title', 'Restablecer contraseña')

@section('content')
    <h2 class="form-title">Restablecer contraseña</h2>
    <p class="form-subtitle">Elige una nueva contraseña para tu cuenta.</p>
    <form method="POST" action="{{ route('password.store') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label class="field-label" for="email">Correo electrónico</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $email) }}" required autofocus>
            @error('email')
                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
            @enderror
        </div>
        <div class="field">
            <label class="field-label" for="password">Nueva contraseña</label>
            <input type="password" class="form-control" id="password" name="password" minlength="8" required>
            <div class="field-help">Mínimo 8 caracteres.</div>
        </div>
        <div class="field">
            <label class="field-label" for="password_confirmation">Confirmar contraseña</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">
            <span class="spinner"></span>
            <span class="btn-label-idle">Guardar contraseña</span>
        </button>
    </form>
@endsection
