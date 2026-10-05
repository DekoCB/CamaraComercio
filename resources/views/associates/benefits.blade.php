@extends('layouts.app')

@section('title', 'Beneficios')

@section('content')
    <x-page-header title="Beneficios de los asociados" subtitle="Catálogo de beneficios institucionales y cuántos le quedan disponibles a cada asociado este año.">
        <x-slot:actions>
            @can('associates.manage')
                <a href="{{ route('benefits.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Nuevo beneficio">
                    {{ icon('plus', 'icon', 16) }} Nuevo beneficio
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @include('associates._tabs', ['active' => 'beneficios'])

    <div class="table-card mb-3">
        <h3 class="form-section-title" style="padding: 0 0 var(--space-3);">Catálogo</h3>
        @if ($benefits->isEmpty())
            <x-empty-state icon="gift" title="Todavía no hay beneficios registrados"
                message="La Cámara aún no confirmó la lista completa — agrégalos aquí a medida que se definan.">
                @can('associates.manage')
                    <a href="{{ route('benefits.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Nuevo beneficio">{{ icon('plus', 'icon', 16) }} Nuevo beneficio</a>
                @endcan
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="is-numeric">Cupo anual</th>
                        <th>Estado</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($benefits as $benefit)
                        <tr class="{{ $benefit->is_active ? '' : 'row-voided' }}">
                            <td class="cell-primary">{{ $benefit->name }}</td>
                            <td class="cell-muted cell-clamp">{{ $benefit->description ?: '-' }}</td>
                            <td class="is-numeric">{{ $benefit->annual_quota }} / año</td>
                            <td>
                                @if ($benefit->is_active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-neutral">Inactivo</span>
                                @endif
                            </td>
                            <td class="is-numeric">
                                @can('associates.manage')
                                    <div class="row-actions">
                                        <a href="{{ route('benefits.edit', $benefit) }}" class="btn btn-ghost btn-icon js-modal-link" data-modal-title="Editar beneficio" title="Editar" aria-label="Editar">
                                            {{ icon('pencil', 'icon', 16) }}
                                        </a>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="table-card">
        <h3 class="form-section-title" style="padding: 0 0 var(--space-3);">Disponibles por asociado este {{ now()->year }}</h3>
        @if ($summary->isEmpty())
            <x-empty-state icon="users" title="No hay asociados activos" message="" />
        @elseif ($benefits->where('is_active', true)->isEmpty())
            <p class="cell-muted" style="margin: 0;">Agrega al menos un beneficio activo en el catálogo para ver el conteo por asociado.</p>
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Asociado</th>
                        @foreach ($benefits->where('is_active', true) as $benefit)
                            <th class="is-numeric">{{ $benefit->name }}</th>
                        @endforeach
                        <th class="is-numeric">Total disponibles</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($summary as $row)
                        <tr>
                            <td class="cell-primary"><a href="{{ route('associates.show', $row->associate) }}" class="link-plain">{{ $row->associate->name }}</a></td>
                            @foreach ($benefits->where('is_active', true) as $benefit)
                                @php($remaining = $row->remaining->get($benefit->id, 0))
                                <td class="is-numeric">
                                    @if ($remaining > 0)
                                        <span class="badge badge-success">{{ $remaining }}</span>
                                    @else
                                        <span class="badge badge-neutral">0</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="is-numeric cell-primary">{{ $row->total }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="cell-muted" style="margin: var(--space-3) 0 0; font-size: var(--text-xs);">
                El número es cuántos usos le quedan este año (cupo anual menos los ya registrados en su ficha). 0 significa que ya agotó el cupo — no que no califica.
            </p>
        @endif
    </div>
@endsection
