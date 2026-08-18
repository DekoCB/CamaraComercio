@extends('layouts.app')

@section('title', $associate ? 'Editar asociado' : 'Registrar asociado')

@section('content')
    <x-page-header :title="$associate ? 'Editar asociado' : 'Registrar asociado'" />

    <div class="card-surface" style="max-width: 560px">
        <form method="POST" action="{{ $action }}" novalidate>
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="field">
                <label class="field-label" for="name">Nombre <span class="required">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required
                       value="{{ old('name', $associate->name ?? '') }}">
                @error('name')
                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
            </div>
            <div class="field">
                <label class="field-label" for="company">Empresa</label>
                <input type="text" class="form-control" id="company" name="company"
                       value="{{ old('company', $associate->company ?? '') }}">
            </div>
            <div class="field">
                <label class="field-label" for="contact_phone">Número de contacto</label>
                <input type="text" class="form-control" id="contact_phone" name="contact_phone"
                       value="{{ old('contact_phone', $associate->contact_phone ?? '') }}">
            </div>
            <div class="field">
                <label class="field-label" for="email">Correo</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                       value="{{ old('email', $associate->email ?? '') }}">
                <div class="field-help">Ingresa un correo válido.</div>
                @error('email')
                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
            </div>
            @if ($associate)
                <div class="form-check field">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                           {{ old('is_active', $associate->is_active) ? 'checked' : '' }}>
                    <label for="is_active" style="font-size: 0.875rem;">Asociado activo</label>
                </div>
            @endif

            <div class="d-flex gap-2" style="margin-top: var(--space-6);">
                <button type="submit" class="btn btn-primary">
                    <span class="spinner"></span>
                    <span class="btn-label-idle">{{ icon('save', 'icon', 16) }} Guardar</span>
                </button>
                <a href="{{ route('associates.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
