@extends('layouts.app')

@section('title', 'Vista previa de importación')

@section('content')
    <x-page-header title="Vista previa de importación" subtitle="Revisa los datos antes de confirmar. Ninguna fila se guarda todavía.">
        <x-slot:actions>
            <form method="POST" action="{{ route('associates.import.cancel') }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Cancelar</button>
            </form>
            <form method="POST" action="{{ route('associates.import.confirm') }}"
                  data-confirm="¿Confirma importar {{ $validCount }} asociado(s)? Esta acción no se puede deshacer."
                  data-confirm-title="¿Confirmar importación?">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm" {{ $validCount === 0 ? 'disabled' : '' }}>
                    <span class="spinner"></span>
                    <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} Confirmar importación</span>
                </button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="d-flex gap-2 mb-3">
        <span class="badge badge-success">{{ $validCount }} listos para importar</span>
        @if ($errorCount > 0)
            <span class="badge badge-danger">{{ $errorCount }} con error (se omitirán)</span>
        @endif
    </div>

    <div class="table-card">
        @if (empty($rows))
            <x-empty-state icon="file-text" title="Sin datos" message="El archivo no tiene filas de datos." />
        @else
            <div class="table-wrap">
                <table class="data-table">
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
                        <tr class="{{ $row['errors'] !== [] ? 'row-danger' : '' }}">
                            <td class="cell-muted">{{ $row['row'] }}</td>
                            <td class="cell-primary">{{ $row['name'] ?? '-' }}</td>
                            <td class="cell-muted">{{ $row['company'] ?? '-' }}</td>
                            <td class="cell-muted">{{ $row['contact_phone'] ?? '-' }}</td>
                            <td class="cell-muted">{{ $row['email'] ?? '-' }}</td>
                            <td>
                                @if ($row['errors'] === [])
                                    <span class="badge badge-success">{{ icon('check', 'icon', 12) }} OK</span>
                                @else
                                    <span class="badge badge-danger" title="{{ implode(' ', $row['errors']) }}">
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
