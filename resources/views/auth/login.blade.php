@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <h1 class="h4 mb-3">Iniciar sesión</h1>
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">Correo electrónico</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Contraseña</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
        <div class="text-center mt-3">
            <a href="{{ route('password.request') }}" class="small">¿Olvidó su contraseña?</a>
        </div>
    </form>
@endsection
