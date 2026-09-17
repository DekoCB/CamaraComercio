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

    {{-- Documentación escaneada (título de propiedad, licencia, convenios...) --}}
    <div class="card-surface mb-3">
        <h3 class="form-section-title" style="padding: 0;">Documentos</h3>

        @can('associates.manage')
            <form method="POST" action="{{ route('associates.documents.store', $associate) }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-end flex-wrap mb-3" novalidate>
                @csrf
                <div class="field" style="margin-bottom: 0;">
                    <label class="field-label" for="doc_type">Tipo de documento</label>
                    <select class="form-select @error('type') is-invalid @enderror" id="doc_type" name="type" style="min-width: 220px;">
                        @foreach ($documentTypes as $key => $label)
                            <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom: 0;">
                    <label class="field-label" for="doc_file">Archivo</label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" id="doc_file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <button type="submit" class="btn btn-secondary">
                    <span class="spinner"></span>
                    <span class="btn-label-idle">{{ icon('upload', 'icon', 16) }} Subir documento</span>
                </button>
            </form>
            <div class="field-help" style="margin-top: -8px; margin-bottom: var(--space-3);">
                PDF, JPG o PNG, máximo 10 MB. Las imágenes se convierten automáticamente a PDF al subirlas.
            </div>
            @error('type')
                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
            @enderror
            @error('file')
                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
            @enderror
        @endcan

        @if ($documents->isEmpty())
            <x-empty-state icon="file-text" title="Sin documentos" message="Todavía no se subió ningún documento para este asociado." />
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Archivo</th>
                        <th>Subido por</th>
                        <th class="is-numeric">Tamaño</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td><span class="badge badge-neutral">{{ $document->typeLabel() }}</span></td>
                            <td class="cell-primary cell-clamp">
                                {{ $document->original_name }}
                                <div class="cell-muted" style="font-size: var(--text-xs); font-weight: 400;">{{ $document->created_at->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="cell-muted">{{ $document->uploadedBy->name ?? '-' }}</td>
                            <td class="is-numeric cell-muted">{{ number_format($document->size / 1024, 0) }} KB</td>
                            <td class="is-numeric">
                                <div class="row-actions">
                                    <a href="{{ $document->url() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-icon" title="Ver documento" aria-label="Ver documento">{{ icon('eye', 'icon', 16) }}</a>
                                    @can('associates.manage')
                                        <form method="POST" action="{{ route('associates.documents.destroy', $document) }}"
                                              data-confirm="¿Eliminar el documento &quot;{{ $document->original_name }}&quot;? Esta acción no se puede deshacer." data-confirm-title="Eliminar documento">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-icon btn-ghost-danger" title="Eliminar" aria-label="Eliminar">{{ icon('trash-2', 'icon', 16) }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Beneficios (ej. uso gratuito anual del auditorio) --}}
    <div class="card-surface mb-3">
        <h3 class="form-section-title" style="padding: 0;">Beneficios</h3>

        @if ($benefits->isEmpty())
            <x-empty-state icon="gift" title="Sin beneficios configurados" message="Todavía no hay beneficios activos en el sistema." />
        @else
            <div class="d-flex gap-2 flex-wrap mb-3">
                @foreach ($benefits as $benefit)
                    @php $used = $benefitUsage[$benefit->id] ?? 0; @endphp
                    <span class="badge {{ $used >= $benefit->annual_quota ? 'badge-neutral' : 'badge-success' }}">
                        {{ $benefit->name }}: {{ $used }}/{{ $benefit->annual_quota }} este año
                    </span>
                @endforeach
            </div>

            @can('associates.manage')
                <form method="POST" action="{{ route('associates.benefitUsages.store', $associate) }}" class="d-flex gap-2 align-items-end flex-wrap mb-3" novalidate>
                    @csrf
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field-label" for="benefit_id">Beneficio</label>
                        <select class="form-select @error('benefit_id') is-invalid @enderror" id="benefit_id" name="benefit_id" style="min-width: 220px;">
                            @foreach ($benefits as $benefit)
                                <option value="{{ $benefit->id }}" {{ (string) old('benefit_id') === (string) $benefit->id ? 'selected' : '' }}>{{ $benefit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="margin-bottom: 0;">
                        <label class="field-label" for="used_at">Fecha de uso</label>
                        <input type="date" class="form-control @error('used_at') is-invalid @enderror" id="used_at" name="used_at" value="{{ old('used_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                    </div>
                    <div class="field" style="margin-bottom: 0; flex: 1 1 200px;">
                        <label class="field-label" for="benefit_notes">Notas</label>
                        <input type="text" class="form-control" id="benefit_notes" name="notes" maxlength="255" value="{{ old('notes') }}">
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <span class="spinner"></span>
                        <span class="btn-label-idle">{{ icon('plus', 'icon', 16) }} Registrar uso</span>
                    </button>
                </form>
                @error('benefit_id')
                    <div class="field-error" style="margin-bottom: var(--space-3);">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
            @endcan
        @endif

        @if ($benefitUsages->isEmpty())
            <x-empty-state icon="gift" title="Sin usos registrados" message="Todavía no se registró ningún uso de beneficio para este asociado." />
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Beneficio</th>
                        <th>Notas</th>
                        <th>Registrado por</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($benefitUsages as $usage)
                        <tr>
                            <td class="cell-muted cell-nowrap">{{ format_date($usage->used_at) }}</td>
                            <td class="cell-primary">{{ $usage->benefit->name }}</td>
                            <td class="cell-muted cell-clamp">{{ $usage->notes ?: '-' }}</td>
                            <td class="cell-muted">{{ $usage->registeredBy->name ?? '-' }}</td>
                            <td class="is-numeric">
                                @can('associates.manage')
                                    <form method="POST" action="{{ route('associates.benefitUsages.destroy', $usage) }}"
                                          data-confirm="¿Eliminar este registro de uso? Esta acción no se puede deshacer." data-confirm-title="Eliminar registro">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-icon btn-ghost-danger" title="Eliminar" aria-label="Eliminar">{{ icon('trash-2', 'icon', 16) }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
