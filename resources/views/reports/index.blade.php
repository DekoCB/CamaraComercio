@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
    <x-page-header title="Reportes" subtitle="Herramientas de análisis de cobranza y deuda pendiente." />

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card-surface h-100">
                <div class="kpi-icon icon-teal" style="margin-bottom: var(--space-4);">{{ icon('wallet', 'icon', 18) }}</div>
                <h2 class="text-h3" style="margin-bottom: var(--space-2);">Lo cobrado en el mes</h2>
                <p class="text-secondary" style="font-size: 0.875rem; margin-bottom: var(--space-4);">Total facturado y cobrado en un período, cantidad de pagos y de asociados que pagaron.</p>
                <form method="GET" action="{{ route('reports.collections') }}" class="d-flex gap-2">
                    <input type="month" name="period" class="form-control" style="max-width: 160px" value="{{ $defaultPeriod }}">
                    <button type="submit" class="btn btn-primary">Ver reporte</button>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-surface h-100">
                <div class="kpi-icon icon-danger" style="margin-bottom: var(--space-4);">{{ icon('alert-triangle', 'icon', 18) }}</div>
                <h2 class="text-h3" style="margin-bottom: var(--space-2);">Deuda pendiente</h2>
                <p class="text-secondary" style="font-size: 0.875rem; margin-bottom: var(--space-4);">Total pendiente, asociados con deuda y distribución por estado, al día de hoy.</p>
                <a href="{{ route('reports.debt') }}" class="btn btn-primary">Ver reporte</a>
            </div>
        </div>
    </div>
@endsection
