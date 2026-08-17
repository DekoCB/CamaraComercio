@extends('layouts.app')

@section('title', 'Nuevo módulo')

@section('content')
    <div class="table-card" style="max-width: 640px">
        <form method="POST" action="{{ $action }}" novalidate>
            @csrf

            <div class="mb-3">
                <label class="form-label" for="code">Código *</label>
                <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" required pattern="[a-z0-9_\-]+"
                       placeholder="p. ej. reports" value="{{ old('code') }}">
                <div class="form-text">Solo minúsculas, números, guiones y guiones bajos.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="name">Nombre *</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" required value="{{ old('name') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="icon">Ícono (bootstrap-icons)</label>
                <input type="text" class="form-control" id="icon" name="icon" placeholder="bi-bar-chart" value="{{ old('icon') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="sort_order">Orden</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}">
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active">Módulo activo</label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ route('admin.modules.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
