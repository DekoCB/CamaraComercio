@extends('layouts.app')

@section('title', 'Sesiones activas')

@section('content')
    <x-page-header title="Administración" subtitle="Usuarios, roles, módulos y sesiones del sistema." />

    @include('admin._nav')

    <div class="table-card">
        @if ($sessions->isEmpty())
            <x-empty-state icon="clock" title="No hay sesiones activas" message="Nadie tiene una sesión abierta en este momento." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Sesión iniciada</th>
                        <th>Última actividad</th>
                        <th>IP</th>
                        <th class="is-numeric">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($sessions as $session)
                        <tr>
                            <td class="cell-primary">
                                {{ $session->user->name }}
                                @if ($session->isCurrent)
                                    <span class="badge badge-info">Esta sesión</span>
                                @endif
                            </td>
                            <td><span class="badge badge-info">{{ $session->user->role->name }}</span></td>
                            <td class="cell-nowrap">
                                @if ($session->loggedInAt)
                                    {{ $session->loggedInAt->diffForHumans() }}
                                    <div class="cell-muted" style="font-size: var(--text-xs);">{{ $session->loggedInAt->format('d/m/Y H:i') }}</div>
                                @else
                                    <span class="cell-muted">-</span>
                                @endif
                            </td>
                            <td class="cell-nowrap cell-muted">{{ $session->lastActivity->diffForHumans() }}</td>
                            <td class="cell-muted">{{ $session->ipAddress ?? '-' }}</td>
                            <td class="is-numeric">
                                <form method="POST" action="{{ route('admin.sessions.destroy', $session->id) }}"
                                      data-confirm="¿Cerrar esta sesión? {{ $session->user->name }} tendrá que volver a iniciar sesión." data-confirm-title="Cerrar sesión">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-ghost-danger btn-sm">
                                        {{ icon('log-out', 'icon', 15) }} Cerrar sesión
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
