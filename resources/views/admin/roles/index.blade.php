@extends('layouts.app')

@section('title', 'Roles')

@section('content')
    <x-page-header title="Administración" subtitle="Usuarios, roles y módulos del sistema.">
        <x-slot:actions>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary btn-sm">{{ icon('plus', 'icon', 16) }} Nuevo rol</a>
        </x-slot:actions>
    </x-page-header>

    @include('admin._nav')

    <div class="table-card">
        @if ($roles->isEmpty())
            <x-empty-state icon="shield" title="Aún no hay roles" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="is-numeric">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="cell-primary">{{ $role->name }}</td>
                            <td class="cell-muted">{{ $role->description ?? '-' }}</td>
                            <td class="is-numeric">
                                <a href="{{ route('admin.roles.access.edit', $role) }}" class="btn btn-ghost btn-sm">{{ icon('key', 'icon', 15) }} Permisos y módulos</a>
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-ghost btn-sm">{{ icon('pencil', 'icon', 15) }} Editar</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
