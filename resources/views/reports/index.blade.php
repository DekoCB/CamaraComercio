@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
    <div class="row g-3">
        <div class="col-md-6">
            <div class="table-card h-100">
                <h2 class="h6"><i class="bi bi-cash-coin"></i> Lo cobrado en el mes</h2>
                <p class="text-muted small">Total facturado y cobrado en un período, cantidad de pagos y de asociados que pagaron.</p>
                <form method="GET" action="{{ route('reports.collections') }}" class="d-flex gap-2">
                    <input type="month" name="period" class="form-control form-control-sm" style="max-width: 160px" value="{{ $defaultPeriod }}">
                    <button type="submit" class="btn btn-sm btn-primary">Ver reporte</button>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-card h-100">
                <h2 class="h6"><i class="bi bi-exclamation-triangle"></i> Deuda pendiente</h2>
                <p class="text-muted small">Total pendiente, asociados con deuda y distribución por estado, al día de hoy.</p>
                <a href="{{ route('reports.debt') }}" class="btn btn-sm btn-primary">Ver reporte</a>
            </div>
        </div>
    </div>
@endsection
