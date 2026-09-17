@extends('layouts.app')

@section('title', 'Editar factura — '.$invoice->associate->name.' ('.$invoice->period.')')

@section('content')
    <x-page-header title="Editar factura" :subtitle="$invoice->associate->name.' · '.format_period($invoice->period)">
        <x-slot:actions>
            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver a la factura
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 560px">
        <p class="cell-muted" style="font-size: var(--text-xs); margin-top: 0;">
            El período y el asociado no se pueden cambiar aquí — genere una nueva factura si la cuota corresponde a otro mes o asociado.
        </p>

        <form method="POST" action="{{ route('invoices.update', $invoice) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="field">
                <label class="field-label" for="receipt_number">N° de comprobante</label>
                <input type="text" class="form-control @error('receipt_number') is-invalid @enderror" id="receipt_number" name="receipt_number" maxlength="50" value="{{ old('receipt_number', $invoice->receipt_number) }}">
                @error('receipt_number')
                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
            </div>
            <div class="field">
                <label class="field-label" for="amount">Monto <span class="required">*</span></label>
                <div class="input-money">
                    <span class="currency-prefix">S/</span>
                    <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror"
                           id="amount" name="amount" required value="{{ old('amount', $invoice->amount) }}">
                </div>
                @error('amount')
                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <div class="field">
                        <label class="field-label" for="issue_date">Fecha de emisión <span class="required">*</span></label>
                        <input type="date" class="form-control @error('issue_date') is-invalid @enderror" id="issue_date" name="issue_date" required
                               value="{{ old('issue_date', $invoice->issue_date->toDateString()) }}">
                        @error('issue_date')
                            <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-6">
                    <div class="field">
                        <label class="field-label" for="due_date">Fecha límite <span class="required">*</span></label>
                        <input type="date" class="form-control @error('due_date') is-invalid @enderror" id="due_date" name="due_date" required
                               value="{{ old('due_date', $invoice->due_date->toDateString()) }}">
                        @error('due_date')
                            <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2" style="margin-top: var(--space-6);">
                <button type="submit" class="btn btn-primary">
                    <span class="spinner"></span>
                    <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} Guardar cambios</span>
                </button>
                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
