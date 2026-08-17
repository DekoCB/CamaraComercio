@extends('layouts.app')

@section('title', 'Generar facturación del mes')

@section('content')
    <div class="table-card" style="max-width: 560px">
        <p class="text-muted small">
            Se generará una factura para cada asociado activo que aún no tenga una factura en el período indicado.
            Los asociados que ya tengan factura para ese período se omiten automáticamente (no se duplican).
        </p>
        <form method="POST" action="{{ route('invoices.store') }}" novalidate
              data-confirm="¿Confirma la generación masiva de facturas para todos los asociados activos? Esta acción no se puede deshacer.">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="period">Período (AAAA-MM) *</label>
                <input type="month" class="form-control @error('period') is-invalid @enderror" id="period" name="period" required
                       value="{{ old('period', $defaultPeriod) }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="amount">Monto por factura *</label>
                <div class="input-group">
                    <span class="input-group-text">S/</span>
                    <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror"
                           id="amount" name="amount" required value="{{ old('amount') }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="issue_date">Fecha de emisión *</label>
                <input type="date" class="form-control @error('issue_date') is-invalid @enderror" id="issue_date" name="issue_date" required
                       value="{{ old('issue_date', now()->toDateString()) }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="due_date">Fecha límite de pago *</label>
                <input type="date" class="form-control @error('due_date') is-invalid @enderror" id="due_date" name="due_date" required
                       value="{{ old('due_date', now()->addDays(15)->toDateString()) }}">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" id="confirm" name="confirm" value="1">
                <label class="form-check-label" for="confirm">
                    Confirmo que deseo generar la facturación masiva para este período.
                </label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Generar facturas</button>
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
