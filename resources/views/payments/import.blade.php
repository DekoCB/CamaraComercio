@extends('layouts.app')

@section('title', 'Importar pagos desde Excel')

@section('content')
    <x-page-header title="Importar pagos desde Excel">
        <x-slot:actions>
            <a href="{{ route('payments.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 560px">
        <p class="text-secondary" style="font-size: 0.875rem; margin-bottom: var(--space-5);">
            Carga un archivo Excel (.xlsx, .xls) o CSV con pagos históricos. La primera fila debe tener encabezados;
            se reconocen: <strong>Asociado</strong> (nombre o RUC), <strong>Período de factura</strong> (AAAA-MM, obligatorio),
            <strong>Monto</strong> (obligatorio), Fecha de pago (obligatoria), Método de pago (opcional: Efectivo, Transferencia, Depósito, Yape, Plin, Tarjeta, Cheque u Otro) y Notas.
            Cada fila debe corresponder a una factura que ya exista en el sistema — este importador no crea facturas nuevas,
            solo registra pagos sobre facturas existentes, igual que si se cargaran una por una desde el detalle de la factura.
            Antes de importar nada podrás revisar una vista previa con los errores detectados.
        </p>
        <form method="POST" action="{{ route('payments.import.preview') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="field">
                <label class="field-label" for="file">Archivo <span class="required">*</span></label>
                <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file"
                       accept=".xlsx,.xls,.csv" required>
                @error('file')
                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
                <div class="field-help">Tamaño máximo: 5 MB.</div>
            </div>
            <div class="d-flex gap-2" style="margin-top: var(--space-6);">
                <button type="submit" class="btn btn-primary">
                    <span class="spinner"></span>
                    <span class="btn-label-idle">{{ icon('upload', 'icon', 16) }} Cargar y previsualizar</span>
                </button>
                <a href="{{ route('payments.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
