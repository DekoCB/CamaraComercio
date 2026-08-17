@extends('layouts.guest')

@section('title', 'Restablecer contraseña')

@section('content')
    <h1 class="h4 mb-3">Restablecer contraseña</h1>
    <form method="POST" action="{{ route('password.store') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3">
            <label class="form-label" for="email">Correo electrónico</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $email) }}" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Nueva contraseña</label>
            <input type="password" class="form-control" id="password" name="password" minlength="8" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Guardar contraseña</button>
    </form>
@endsection
