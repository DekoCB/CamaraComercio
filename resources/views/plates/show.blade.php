@extends('layouts.app')

@section('title', 'Trámite — '.$plate->requesterLabel())

@section('content')
    <x-page-header :title="$plate->procedureLabel().' — '.$plate->requesterLabel()" :subtitle="$plate->plate_number ?? 'Sin placa registrada'">
        <x-slot:actions>
            <a href="{{ route('plates.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
            @if ($plate->associate)
                <a href="{{ route('associates.show', $plate->associate) }}" class="btn btn-secondary btn-sm">
                    {{ icon('users', 'icon', 16) }} Ficha del asociado
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card-surface mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge badge-neutral">{{ $plate->receiptLabel() }}</span>
                    @if ($plate->receipt_number)
                        <span class="cell-muted">N° {{ $plate->receipt_number }}</span>
                    @endif
                </div>

                <dl class="detail-grid">
                    <div class="detail-item"><dt>Fecha del trámite</dt><dd>{{ format_date($plate->issued_at) }}</dd></div>
                    <div class="detail-item"><dt>Tipo de trámite</dt><dd>{{ $plate->procedureLabel() }}</dd></div>
                    @if ($plate->other_description)
                        <div class="detail-item is-wide"><dt>Descripción</dt><dd>{{ $plate->other_description }}</dd></div>
                    @endif
                    <div class="detail-item"><dt>Placa</dt><dd>{{ $plate->plate_number ?? '-' }}</dd></div>
                    <div class="detail-item"><dt>Vehículo</dt><dd>{{ $plate->vehicle_description ?? '-' }}</dd></div>
                    <div class="detail-item"><dt>Solicitante</dt><dd>{{ $plate->requesterLabel() }}</dd></div>
                    <div class="detail-item"><dt>Costo</dt><dd>{{ format_money($plate->amount) }}</dd></div>
                    <div class="detail-item"><dt>Registrado por</dt><dd>{{ $plate->registeredBy->name ?? '-' }}</dd></div>
                </dl>

                @if ($plate->notes)
                    <div class="mt-3">
                        <div class="field-label">Notas</div>
                        <p class="cell-muted" style="margin: 0;">{{ $plate->notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
