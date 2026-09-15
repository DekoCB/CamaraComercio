@extends('layouts.app')

@section('title', 'Ficha del asociado')

@php
    $fmtDate = fn ($d) => $d ? $d->format('d/m/Y') : '-';
    $fmtMoney = fn ($v) => $v !== null ? 'S/ '.number_format((float) $v, 2) : '-';
    $fmtPeriod = function (?string $p) {
        if (! $p) {
            return '-';
        }
        [$y, $m] = explode('-', $p);
        $months = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        return ($months[(int) $m] ?? $m).' '.$y;
    };

    // Same grouping as the form / the Cámara's master Excel.
    $sections = [
        'Datos generales' => [
            ['Razón social', $associate->name],
            ['RUC', $associate->ruc],
            ['Nombre comercial', $associate->company],
            ['Tipo de persona', $associate->person_type],
            ['Sectorista', $associate->sectorista],
            ['Categoría', $associate->category],
            ['Monto a pagar', $fmtMoney($associate->monthly_fee)],
            ['Último mes pagado', $fmtPeriod($lastPaidPeriod)],
            ['Fecha de ingreso', $fmtDate($associate->joined_at)],
            ['Fecha de aniversario', $fmtDate($associate->anniversary_date)],
            ['Correo de la empresa', $associate->email],
            ['Teléfono de la empresa', $associate->contact_phone],
        ],
        'Direcciones' => [
            ['Dirección de facturación', $associate->billing_address],
            ['Distrito', $associate->billing_district],
            ['Dirección de correspondencia', $associate->mailing_address],
            ['Distrito de correspondencia', $associate->mailing_district],
        ],
        'Clasificación' => [
            ['Según su tamaño', $associate->company_size],
            ['Según su actividad', $associate->activity_type],
            ['Comité sectorial', $associate->sector_committee],
            ['CIIU', $associate->ciiu],
            ['Sub sector', $associate->sub_sector, 'wide'],
        ],
        'Representante legal' => [
            ['Nombre completo', $associate->legal_rep_name],
            ['DNI N°', $associate->legal_rep_dni],
            ['Género', $associate->legal_rep_gender],
            ['Cumpleaños', $fmtDate($associate->legal_rep_birthday)],
            ['Celular', $associate->legal_rep_phone],
            ['Correo', $associate->legal_rep_email],
        ],
        'Representante ante la CCH' => [
            ['Nombre completo', $associate->cch_rep_name],
            ['DNI N°', $associate->cch_rep_dni],
            ['Género', $associate->cch_rep_gender],
            ['Cumpleaños', $fmtDate($associate->cch_rep_birthday)],
            ['Celular', $associate->cch_rep_phone],
            ['Correo', $associate->cch_rep_email],
        ],
    ];
@endphp

@section('content')
    <x-page-header :title="$associate->name" :subtitle="$associate->company && $associate->company !== $associate->name ? $associate->company : ($associate->ruc ? 'RUC '.$associate->ruc : null)">
        <x-slot:actions>
            <a href="{{ route('associates.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
            @can('billing.view')
                <a href="{{ route('invoices.index', ['associate_id' => $associate->id]) }}" class="btn btn-secondary btn-sm">
                    {{ icon('file-text', 'icon', 16) }} Facturas
                </a>
            @endcan
            @can('associates.manage')
                <a href="{{ route('associates.edit', $associate) }}" class="btn btn-primary btn-sm">
                    {{ icon('pencil', 'icon', 16) }} Editar
                </a>
                <form method="POST" action="{{ route('associates.destroy', $associate) }}"
                      data-confirm="¿Eliminar al asociado &quot;{{ $associate->name }}&quot;? Esta acción no se puede deshacer."
                      data-confirm-title="¿Eliminar asociado?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary btn-ghost-danger btn-sm">
                        {{ icon('trash-2', 'icon', 16) }} Eliminar
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface mb-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            @if ($associate->image_path)
                <img src="{{ $associate->imageUrl() }}" alt="" style="width: 72px; height: 72px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            @else
                <div style="width: 72px; height: 72px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); background: var(--color-bg); color: var(--color-text-tertiary);">
                    {{ icon('building-2', 'icon', 32) }}
                </div>
            @endif
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    @include('associates._status_badge', ['status' => $associate->status])
                    @if ($associate->sectorista)
                        <span class="badge badge-neutral">Sectorista: {{ $associate->sectorista }}</span>
                    @endif
                    @if ($associate->category)
                        <span class="badge badge-neutral">Cat. {{ $associate->category }}</span>
                    @endif
                </div>
                <div class="cell-muted" style="font-size: 0.875rem;">
                    Registrado en el sistema el {{ $associate->created_at->format('d/m/Y') }}
                </div>
            </div>
        </div>
    </div>

    @foreach ($sections as $title => $items)
        <div class="card-surface mb-3">
            <h3 class="form-section-title" style="padding: 0;">{{ $title }}</h3>
            <dl class="detail-grid">
                @foreach ($items as $item)
                    <div class="detail-item {{ ($item[2] ?? null) === 'wide' ? 'is-wide' : '' }}">
                        <dt>{{ $item[0] }}</dt>
                        <dd>{{ $item[1] !== null && $item[1] !== '' ? $item[1] : '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endforeach

    <div class="card-surface mb-3">
        <h3 class="form-section-title" style="padding: 0;">Observaciones</h3>
        <p style="margin: 0; white-space: pre-line;">{{ $associate->notes ?: '-' }}</p>
    </div>
@endsection
