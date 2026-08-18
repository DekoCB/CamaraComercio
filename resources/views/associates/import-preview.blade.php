@extends('layouts.app')

@section('title', 'Vista previa de importación')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <span class="badge bg-success-subtle text-success-emphasis">{{ $validCount }} listos para importar</span>
            @if ($errorCount > 0)
                <span class="badge bg-danger-subtle text-danger-emphasis">{{ $errorCount }} con error (se omitirán)</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('associates.import.confirm') }}"
                  data-confirm="¿Confirma importar {{ $validCount }} asociado(s)? Esta acción no se puede deshacer.">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm" {{ $validCount === 0 ? 'disabled' : '' }}>
                    <i class="bi bi-check-lg"></i> Confirmar importación
                </button>
            </form>
            <form method="POST" action="{{ route('associates.import.cancel') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Cancelar</button>
            </form>
        </div>
    </div>

    <div class="table-card">
        @if (empty($rows))
            <div class="empty-state">
                <p class="mb-0">El archivo no tiene filas de datos.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Fila</th>
                        <th>Nombre</th>
                        <th>Empresa</th>
                        <th>Contacto</th>
                        <th>Correo</th>
                        <th>Estado</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ $row['errors'] !== [] ? 'table-danger' : '' }}">
                            <td>{{ $row['row'] }}</td>
                            <td>{{ $row['name'] ?? '-' }}</td>
                            <td>{{ $row['company'] ?? '-' }}</td>
                            <td>{{ $row['contact_phone'] ?? '-' }}</td>
                            <td>{{ $row['email'] ?? '-' }}</td>
                            <td>
                                @if ($row['errors'] === [])
                                    <span class="badge bg-success-subtle text-success-emphasis">OK</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger-emphasis" title="{{ implode(' ', $row['errors']) }}">
                                        {{ implode(' ', $row['errors']) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
