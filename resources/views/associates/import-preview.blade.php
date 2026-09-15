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
                  data-confirm="¿Confirma importar {{ $validCount }} asociado(s){{ $paymentCount > 0 ? " y registrar {$paymentCount} pago(s) de sus aportes" : '' }}? Esta acción no se puede deshacer."
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
        @if ($updateCount > 0)
            <span class="badge badge-info">{{ $updateCount }} ya existen (se actualizarán)</span>
        @endif
        @if ($paymentCount > 0)
            <span class="badge badge-neutral">{{ $paymentCount }} aportes mensuales se registrarán como pagos</span>
        @endif
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
                        <th>Razón social</th>
                        <th>RUC</th>
                        <th>Nombre comercial</th>
                        <th>Sectorista</th>
                        <th>Cat.</th>
                        <th class="is-numeric">Monto</th>
                        <th>Correo</th>
                        <th>Rep. legal</th>
                        <th>Estado</th>
                        <th>Aportes</th>
                        <th>Acción</th>
                        <th>Validación</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ $row['errors'] !== [] ? 'row-danger' : '' }}">
                            <td class="cell-muted">{{ $row['row'] }}</td>
                            <td class="cell-primary">{{ $row['name'] ?? '-' }}</td>
                            <td class="cell-muted">{{ $row['ruc'] ?? '-' }}</td>
                            <td class="cell-muted">{{ $row['company'] ?? '-' }}</td>
                            <td class="cell-muted">{{ $row['sectorista'] ?? '-' }}</td>
                            <td class="cell-muted">{{ $row['category'] ?? '-' }}</td>
                            <td class="is-numeric cell-muted">{{ isset($row['monthly_fee']) ? 'S/ '.number_format((float) $row['monthly_fee'], 2) : '-' }}</td>
                            <td class="cell-muted">{{ $row['email'] ?? '-' }}</td>
                            <td class="cell-muted">{{ $row['legal_rep_name'] ?? '-' }}</td>
                            <td>@include('associates._status_badge', ['status' => $row['status'] ?? \App\Models\Associate::STATUS_ACTIVO])</td>
                            <td class="cell-muted cell-nowrap">
                                @if ($row['contributions'] === [])
                                    -
                                @else
                                    {{ count($row['contributions']) }} meses
                                    <span class="text-tertiary">({{ $row['contributions'][0]['period'] }} → {{ end($row['contributions'])['period'] }})</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['action'] === \App\Services\AssociateImportService::ACTION_UPDATE)
                                    <span class="badge badge-info">Actualizar</span>
                                @else
                                    <span class="badge badge-neutral">Nuevo</span>
                                @endif
                            </td>
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
