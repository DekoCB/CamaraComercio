@extends('layouts.app')

@section('title', 'Alquiler — '.$rental->space->name.' ('.$rental->associate->name.')')

@section('content')
    <x-page-header :title="$rental->space->name" :subtitle="$rental->associate->name">
        <x-slot:actions>
            <a href="{{ route('rentals.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
            <a href="{{ route('associates.show', $rental->associate) }}" class="btn btn-secondary btn-sm">
                {{ icon('users', 'icon', 16) }} Ficha del asociado
            </a>
            <a href="{{ route('rentals.pdf', $rental) }}" class="btn btn-secondary btn-sm">
                {{ icon('file-down', 'icon', 16) }} {{ $rental->status === \App\Models\Rental::STATUS_COTIZADA ? 'Descargar cotización' : 'Descargar comprobante' }}
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($rental->isCancelled())
        <div class="voided-panel mb-3">
            {{ icon('x-circle', 'icon', 24) }}
            <div>
                <strong>Alquiler cancelado</strong>
                <div class="cell-muted" style="font-size: var(--text-xs);">
                    {{ format_date($rental->cancelled_at) }}{{ $rental->cancelledBy ? ' · '.$rental->cancelledBy->name : '' }} · Motivo: {{ $rental->cancel_reason }}
                </div>
            </div>
        </div>
    @endif

    @error('status')
        <div class="voided-panel mb-3">{{ icon('alert-triangle', 'icon', 22) }} <div>{{ $message }}</div></div>
    @enderror

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card-surface mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <x-status-badge :status="$rental->status" />
                    @if ($rental->purpose)
                        <span class="cell-muted">{{ $rental->purpose }}</span>
                    @endif
                </div>

                <dl class="detail-grid">
                    <div class="detail-item"><dt>Espacio</dt><dd>{{ $rental->space->name }}</dd></div>
                    <div class="detail-item"><dt>Asociado</dt><dd><a href="{{ route('associates.show', $rental->associate) }}" class="link-plain">{{ $rental->associate->name }}</a></dd></div>
                    <div class="detail-item"><dt>Inicio</dt><dd>{{ $rental->starts_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="detail-item"><dt>Fin</dt><dd>{{ $rental->ends_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="detail-item"><dt>Monto</dt><dd>{{ format_money($rental->amount) }}</dd></div>
                    <div class="detail-item"><dt>Registrado por</dt><dd>{{ $rental->creator->name ?? '-' }}</dd></div>
                </dl>

                @if ($rental->notes)
                    <div class="mt-3">
                        <div class="field-label">Notas</div>
                        <p class="cell-muted" style="margin: 0;">{{ $rental->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        @can('rentals.manage')
            <div class="col-lg-4">
                <div class="card-surface">
                    <h3 class="form-section-title" style="padding: 0;">Acciones</h3>
                    <div class="d-flex flex-column gap-2">
                        @if (! $rental->isCancelled() && $rental->status !== \App\Models\Rental::STATUS_FACTURADA)
                            <a href="{{ route('rentals.edit', $rental) }}" class="btn btn-secondary btn-sm">{{ icon('pencil', 'icon', 15) }} Editar</a>
                        @endif

                        @if ($rental->status === \App\Models\Rental::STATUS_COTIZADA)
                            <form method="POST" action="{{ route('rentals.confirm', $rental) }}" data-confirm="¿Confirmar esta reserva? Se validará que el espacio esté libre en ese horario." data-confirm-title="Confirmar reserva">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-primary btn-sm w-100">{{ icon('check', 'icon', 15) }} Confirmar reserva</button>
                            </form>
                        @endif

                        @if ($rental->status === \App\Models\Rental::STATUS_CONFIRMADA)
                            <form method="POST" action="{{ route('rentals.bill', $rental) }}" data-confirm="¿Marcar este alquiler como facturado?" data-confirm-title="Marcar como facturado">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-primary btn-sm w-100">{{ icon('receipt', 'icon', 15) }} Marcar como facturado</button>
                            </form>
                        @endif

                        @if (! $rental->isCancelled() && $rental->status !== \App\Models\Rental::STATUS_FACTURADA)
                            <details class="void-payment-details">
                                <summary class="btn btn-ghost btn-ghost-danger btn-sm" style="cursor: pointer; display: inline-flex;">
                                    {{ icon('x-circle', 'icon', 15) }} Cancelar alquiler
                                </summary>
                                <form method="POST" action="{{ route('rentals.cancel', $rental) }}" class="mt-2"
                                      data-confirm="¿Cancelar este alquiler? Esta acción no se puede deshacer." data-confirm-title="Cancelar alquiler">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" class="form-control" name="reason" maxlength="255" required placeholder="Motivo de la cancelación">
                                    <button type="submit" class="btn btn-danger btn-sm mt-2">Confirmar cancelación</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endsection
