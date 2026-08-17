@extends('layouts.app')

@section('title', 'Roles')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        @include('admin._nav')
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo rol
        </a>
    </div>

    <div class="table-card">
        @if ($roles->isEmpty())
            <div class="empty-state"><i class="bi bi-shield fs-1"></i><p class="mb-0 mt-2">Aún no hay roles.</p></div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>{{ $role->name }}</td>
                            <td class="text-muted">{{ $role->description ?? '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.roles.access.edit', $role) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-key"></i> Permisos y módulos
                                </a>
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
