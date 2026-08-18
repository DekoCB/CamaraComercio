@extends('layouts.app')

@section('title', 'Importar asociados desde Excel')

@section('content')
    <div class="table-card" style="max-width: 640px">
        <p class="text-muted small">
            Cargue un archivo Excel (.xlsx, .xls) o CSV con sus asociados. La primera fila debe tener encabezados;
            se reconocen: <strong>Nombre</strong> (obligatoria), Empresa, Contacto y Correo.
            Antes de importar nada podrá revisar una vista previa con los errores detectados.
        </p>
        <form method="POST" action="{{ route('associates.import.preview') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="mb-3">
                <label class="form-label" for="file">Archivo *</label>
                <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file"
                       accept=".xlsx,.xls,.csv" required>
                @error('file')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Tamaño máximo: 5 MB.</div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Cargar y previsualizar</button>
                <a href="{{ route('associates.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
