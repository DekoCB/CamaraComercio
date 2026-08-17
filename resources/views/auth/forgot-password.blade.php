@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
    <h1 class="h4 mb-3">Recuperar contraseña</h1>
    <p class="text-muted small">Ingrese su correo y, si está registrado, le enviaremos un enlace para restablecer su contraseña.</p>
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">Correo electrónico</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100">Enviar enlace</button>
        <div class="text-center mt-3">
            <a href="{{ route('login') }}" class="small">Volver a iniciar sesión</a>
        </div>
    </form>
@endsection
