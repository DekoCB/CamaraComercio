@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
    <h2 class="form-title">Recuperar contraseña</h2>
    <p class="form-subtitle">Ingresa tu correo y, si está registrado, te enviaremos un enlace para restablecer tu contraseña.</p>
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="field">
            <label class="field-label" for="email">Correo electrónico</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" required autofocus>
            @error('email')
                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">
            <span class="spinner"></span>
            <span class="btn-label-idle">Enviar enlace</span>
        </button>
        <div class="text-center" style="margin-top: var(--space-4);">
            <a href="{{ route('login') }}" style="font-size: 0.8125rem;">Volver a iniciar sesión</a>
        </div>
    </form>
@endsection
