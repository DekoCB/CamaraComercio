@extends('layouts.app')

@section('title', $associate ? 'Editar asociado' : 'Registrar asociado')

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
                       value="{{ old('name', $associate->name ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="company">Empresa</label>
                <input type="text" class="form-control" id="company" name="company"
                       value="{{ old('company', $associate->company ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="contact_phone">Número de contacto</label>
                <input type="text" class="form-control" id="contact_phone" name="contact_phone"
                       value="{{ old('contact_phone', $associate->contact_phone ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Correo</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                       value="{{ old('email', $associate->email ?? '') }}">
            </div>
            @if ($associate)
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                           {{ old('is_active', $associate->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Asociado activo</label>
                </div>
            @endif

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ route('associates.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
