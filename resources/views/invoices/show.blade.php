@extends('layouts.app')

@section('title', 'Factura — '.$invoice->associate->name.' ('.$invoice->period.')')

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="table-card mb-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">{{ $invoice->associate->name }}</h2>
                        <div class="text-muted small">{{ $invoice->associate->company }}</div>
                    </div>
                    @include('invoices._status_badge', ['invoice' => $invoice])
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Período</div>
                        <div class="fw-semibold">{{ $invoice->period }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Emisión</div>
                        <div class="fw-semibold">{{ format_date($invoice->issue_date) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Vence</div>
                        <div class="fw-semibold">{{ format_date($invoice->due_date) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Generada por</div>
                        <div class="fw-semibold">{{ $invoice->creator->name ?? '-' }}</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-4">
                        <div class="text-muted small">Monto</div>
                        <div class="fs-5 fw-bold">{{ format_money($invoice->amount) }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Pagado</div>
                        <div class="fs-5 fw-bold text-success">{{ format_money($invoice->paid_total) }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Saldo</div>
                        <div class="fs-5 fw-bold {{ $invoice->balance() > 0 ? 'text-danger' : '' }}">{{ format_money($invoice->balance()) }}</div>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <h2 class="h6 mb-3">Historial de pagos</h2>
                @if ($invoice->payments->isEmpty())
                    <div class="empty-state py-3">
                        <p class="mb-0">Todavía no se registran pagos para esta factura.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Registrado por</th>
                                <th>Notas</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($invoice->payments->sortByDesc('paid_at') as $payment)
                                <tr>
                                    <td>{{ format_date($payment->paid_at) }}</td>
                                    <td>{{ format_money($payment->amount) }}</td>
                                    <td>{{ $payment->registeredBy->name ?? '-' }}</td>
                                    <td class="text-muted">{{ $payment->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            @can('payments.register')
                <div class="table-card">
                    <h2 class="h6 mb-3">Registrar pago</h2>
                    @if ($invoice->balance() <= 0)
                        <p class="text-muted small mb-0">Esta factura ya está pagada en su totalidad.</p>
                    @else
                        <form method="POST" action="{{ route('payments.store', $invoice) }}" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="amount">Monto *</label>
                                <div class="input-group">
                                    <span class="input-group-text">S/</span>
                                    <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance() }}"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           id="amount" name="amount" required value="{{ old('amount') }}">
                                </div>
                                <div class="form-text">Saldo pendiente: {{ format_money($invoice->balance()) }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="paid_at">Fecha de pago *</label>
                                <input type="date" class="form-control @error('paid_at') is-invalid @enderror" id="paid_at" name="paid_at" required
                                       value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="notes">Notas</label>
                                <input type="text" class="form-control" id="notes" name="notes" maxlength="255" value="{{ old('notes') }}">
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cash-coin"></i> Registrar pago</button>
                        </form>
                    @endif
                </div>
            @endcan
        </div>
    </div>
@endsection
