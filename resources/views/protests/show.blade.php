@extends('layouts.app')

@section('title', 'Registro — '.$protest->debtor_name)

@section('content')
    <x-page-header :title="$protest->typeLabel().' — '.$protest->debtor_name" :subtitle="'Vía '.$protest->channelLabel()">
        <x-slot:actions>
            <a href="{{ route('protests.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
            @if ($protest->associate)
                <a href="{{ route('associates.show', $protest->associate) }}" class="btn btn-secondary btn-sm">
                    {{ icon('users', 'icon', 16) }} Ficha del asociado
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($protest->isRegularized())
        <div class="voided-panel mb-3">
            {{ icon('check-circle-2', 'icon', 24) }}
            <div>
                <strong>Registro regularizado</strong>
                <div class="cell-muted" style="font-size: var(--text-xs);">
                    {{ format_date($protest->regularized_at) }}{{ $protest->regularizedBy ? ' · '.$protest->regularizedBy->name : '' }}
                    @if ($protest->regularization_notes) · {{ $protest->regularization_notes }} @endif
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
                    <x-status-badge :status="$protest->status" />
                    @if ($protest->instrument_type)
                        <span class="cell-muted">{{ \App\Models\Protest::INSTRUMENT_TYPES[$protest->instrument_type] ?? $protest->instrument_type }}</span>
                    @endif
                </div>

                <dl class="detail-grid">
                    <div class="detail-item"><dt>Fecha de registro</dt><dd>{{ format_date($protest->registered_at) }}</dd></div>
                    <div class="detail-item"><dt>Vía</dt><dd>{{ $protest->channelLabel() }}</dd></div>
                    <div class="detail-item"><dt>Deudor</dt><dd>{{ $protest->debtor_name }}{{ $protest->debtor_document ? ' — '.$protest->debtor_document : '' }}</dd></div>
                    <div class="detail-item"><dt>Acreedor</dt><dd>{{ $protest->creditor_name }}{{ $protest->creditor_document ? ' — '.$protest->creditor_document : '' }}</dd></div>
                    <div class="detail-item"><dt>Asociado que solicita</dt><dd>{{ $protest->associate->name ?? '— (no socio)' }}</dd></div>
                    <div class="detail-item"><dt>Monto cobrado</dt><dd>{{ format_money($protest->amount) }}</dd></div>
                    <div class="detail-item"><dt>Registrado por</dt><dd>{{ $protest->creator->name ?? '-' }}</dd></div>
                </dl>

                @if ($protest->notes)
                    <div class="mt-3">
                        <div class="field-label">Notas</div>
                        <p class="cell-muted" style="margin: 0;">{{ $protest->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        @can('protests.manage')
            @if (! $protest->isRegularized())
                <div class="col-lg-4">
                    <div class="card-surface">
                        <h3 class="form-section-title" style="padding: 0;">Acciones</h3>
                        <form method="POST" action="{{ route('protests.regularize', $protest) }}" data-confirm="¿Marcar este registro como regularizado? Significa que el título o la deuda ya fue pagada." data-confirm-title="Regularizar registro">
                            @csrf
                            @method('PUT')
                            <div class="field">
                                <label class="field-label" for="notes">Notas (opcional)</label>
                                <input type="text" class="form-control" id="notes" name="notes" maxlength="255" placeholder="Ej. pagado el 20/09, comprobante N°...">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100" style="margin-top: var(--space-3);">{{ icon('check', 'icon', 15) }} Marcar como regularizado</button>
                        </form>
                    </div>
                </div>
            @endif
        @endcan
    </div>
@endsection
