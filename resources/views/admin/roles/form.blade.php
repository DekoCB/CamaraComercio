@extends('layouts.app')

@section('title', $role ? 'Editar rol' : 'Nuevo rol')

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
                       value="{{ old('name', $role->name ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Descripción</label>
                <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $role->description ?? '') }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
