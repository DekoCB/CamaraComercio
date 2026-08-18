@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
    <x-page-header title="Administración" subtitle="Usuarios, roles y módulos del sistema.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">{{ icon('user-round-plus', 'icon', 16) }} Nuevo usuario</a>
        </x-slot:actions>
    </x-page-header>

    @include('admin._nav')

    <div class="table-card">
        @if ($users->isEmpty())
            <x-empty-state icon="users" title="Aún no hay usuarios" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="is-numeric">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="cell-primary">{{ $user->name }}</td>
                            <td class="cell-muted">{{ $user->email }}</td>
                            <td><span class="badge badge-info">{{ $user->role->name }}</span></td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-neutral">Inactivo</span>
                                @endif
                            </td>
                            <td class="is-numeric">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost btn-sm">{{ icon('pencil', 'icon', 15) }} Editar</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
