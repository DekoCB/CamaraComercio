@extends('layouts.app')

@section('title', 'Importar asociados desde Excel')

@section('content')
    <x-page-header title="Importar asociados desde Excel" />

    <div class="card-surface" style="max-width: 560px">
        <p class="text-secondary" style="font-size: 0.875rem; margin-bottom: var(--space-5);">
            Carga un archivo Excel (.xlsx, .xls) o CSV con tus asociados. La primera fila debe tener encabezados;
            se reconocen: <strong>Nombre</strong> (obligatoria), Empresa, Contacto y Correo.
            Antes de importar nada podrás revisar una vista previa con los errores detectados.
        </p>
        <form method="POST" action="{{ route('associates.import.preview') }}" enctype="multipart/form-data" novalidate>
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
                <a href="{{ route('associates.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
