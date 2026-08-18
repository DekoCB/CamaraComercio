@extends('layouts.app')

@section('title', 'Asociados')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form class="d-flex gap-2" method="GET" action="{{ route('associates.index') }}">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Buscar por nombre, empresa, contacto o correo"
                   value="{{ $term }}" style="min-width: 280px">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
        @can('associates.manage')
            <div class="d-flex gap-2">
                <a href="{{ route('associates.import.create') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-file-earmark-excel"></i> Importar desde Excel
                </a>
                <a href="{{ route('associates.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Registrar asociado
                </a>
            </div>
        @endcan
    </div>

    <div class="table-card">
        @if ($associates->isEmpty())
            <div class="empty-state">
                <i class="bi bi-people fs-1"></i>
                <p class="mb-0 mt-2">No se encontraron asociados @if($term !== '') para "{{ $term }}" @endif.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Empresa</th>
                        <th>Contacto</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($associates as $associate)
                        <tr>
                            <td>{{ $associate->name }}</td>
                            <td>{{ $associate->company ?? '-' }}</td>
                            <td>{{ $associate->contact_phone ?? '-' }}</td>
                            <td>{{ $associate->email ?? '-' }}</td>
                            <td>
                                @if ($associate->is_active)
                                    <span class="badge bg-success-subtle text-success-emphasis">Activo</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('billing.view')
                                    <a href="{{ route('invoices.index', ['associate_id' => $associate->id]) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-receipt"></i> Facturas
                                    </a>
                                @endcan
                                @can('associates.manage')
                                    <a href="{{ route('associates.edit', $associate) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $associates->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
