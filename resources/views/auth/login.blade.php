@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <h2 class="form-title">Iniciar sesión</h2>
    <p class="form-subtitle">Ingresa tus credenciales para acceder al sistema.</p>
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div class="field">
            <label class="field-label" for="email">Correo electrónico</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
            @enderror
        </div>
        <div class="field">
            <label class="field-label" for="password">Contraseña</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="form-check" style="margin-bottom: var(--space-5);">
            <input type="checkbox" class="form-check-input" id="remember" name="remember">
            <label for="remember" style="font-size: 0.875rem; color: var(--color-text-secondary);">Recordarme</label>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">
            <span class="spinner"></span>
            <span class="btn-label-idle">Iniciar sesión</span>
        </button>
        <div class="text-center" style="margin-top: var(--space-4);">
            <a href="{{ route('password.request') }}" style="font-size: 0.8125rem;">¿Olvidaste tu contraseña?</a>
        </div>
    </form>
@endsection
