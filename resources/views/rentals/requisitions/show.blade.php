@extends('layouts.app')

@section('title', 'Requerimiento — '.$requisition->documentNumber())

@section('content')
    <x-page-header :title="'Requerimiento N° '.$requisition->documentNumber()" :subtitle="$requisition->subject">
        <x-slot:actions>
            <a href="{{ route('rentals.requisitions.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
            <a href="{{ route('rentals.requisitions.pdf', $requisition) }}" class="btn btn-secondary btn-sm">
                {{ icon('file-down', 'icon', 16) }} Descargar PDF
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card-surface mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge badge-neutral">{{ $requisition->typeLabel() }}</span>
                </div>

                <dl class="detail-grid">
                    <div class="detail-item"><dt>Fecha</dt><dd>{{ format_date($requisition->issued_at) }}</dd></div>
                    <div class="detail-item"><dt>Dirigido a</dt><dd>{{ $requisition->recipient_name }}{{ $requisition->recipient_role ? ' — '.$requisition->recipient_role : '' }}</dd></div>
                    <div class="detail-item"><dt>Área solicitante</dt><dd>{{ $requisition->requester_area ?? '-' }}</dd></div>
                    <div class="detail-item"><dt>Registrado por</dt><dd>{{ $requisition->creator->name ?? '-' }}</dd></div>
                    <div class="detail-item is-wide"><dt>Asunto</dt><dd>{{ $requisition->subject }}</dd></div>
                    <div class="detail-item"><dt>Titular</dt><dd>{{ $requisition->beneficiary_name }}</dd></div>
                    @if ($requisition->provider_ruc)
                        <div class="detail-item"><dt>RUC del proveedor</dt><dd>{{ $requisition->provider_ruc }}</dd></div>
                    @endif
                    @if ($requisition->bank_details)
                        <div class="detail-item is-wide"><dt>Datos bancarios</dt><dd style="white-space: pre-line;">{{ $requisition->bank_details }}</dd></div>
                    @endif
                    <div class="detail-item"><dt>Monto total</dt><dd>{{ format_money($requisition->total()) }}</dd></div>
                </dl>

                @if ($requisition->notes)
                    <div class="mt-3">
                        <div class="field-label">Notas</div>
                        <p class="cell-muted" style="margin: 0;">{{ $requisition->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="card-surface mb-3">
                <h3 class="form-section-title" style="padding: 0;">Ítems</h3>
                <div class="table-wrap">
                    <table class="data-table data-table-compact">
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>N° comprobante</th>
                            <th>Descripción</th>
                            <th class="is-numeric">Cant.</th>
                            <th class="is-numeric">Pr. unitario</th>
                            <th class="is-numeric">Importe</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($requisition->items as $item)
                            <tr>
                                <td class="cell-nowrap">{{ $item->item_date ? format_date($item->item_date) : '-' }}</td>
                                <td>{{ $item->reference ?? '-' }}</td>
                                <td>{{ $item->description }}</td>
                                <td class="is-numeric">{{ $item->quantity ?? '-' }}</td>
                                <td class="is-numeric cell-money">{{ $item->unit_price ? format_money($item->unit_price) : '-' }}</td>
                                <td class="is-numeric cell-money">{{ format_money($item->amount) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
