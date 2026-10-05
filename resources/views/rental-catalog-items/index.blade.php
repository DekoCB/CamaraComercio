@extends('layouts.app')

@section('title', 'Equipos y servicios de Alquileres')

@section('content')
    <x-page-header title="Equipos y servicios" subtitle="Catálogo de equipos que se ofrecen junto con el alquiler de un espacio, y la cuenta bancaria que aparece en cada cotización.">
        <x-slot:actions>
            <a href="{{ route('rentals.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver a Alquileres
            </a>
            <a href="{{ route('rental-catalog-items.create') }}" class="btn btn-primary btn-sm js-modal-link" data-modal-title="Nuevo ítem">
                {{ icon('plus', 'icon', 16) }} Nuevo ítem
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface mb-3" style="max-width: 560px;">
        <h3 class="form-section-title" style="padding: 0;">Cuenta bancaria oficial</h3>
        <p class="cell-muted" style="font-size: var(--text-xs); margin-top: -4px;">Aparece en "Modalidad de pago" de cada cotización — se puede anular puntualmente en un alquiler específico si hace falta.</p>
        <form method="POST" action="{{ route('rental-catalog-items.bankAccount.update') }}" novalidate>
            @csrf
            @method('PUT')
            <div class="field">
                <label class="field-label" for="bank_account_official">Datos de la cuenta</label>
                <textarea class="form-control @error('bank_account_official') is-invalid @enderror" id="bank_account_official" name="bank_account_official" rows="4" maxlength="500" placeholder="CUENTA OFICIAL BBVA:&#10;CUENTA BBVA: 0011-0235-02019704-13&#10;CCI: 011-235-000201970413-95&#10;A NOMBRE: ...">{{ old('bank_account_official', $bankAccountDefault) }}</textarea>
                @error('bank_account_official')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">{{ icon('check', 'icon', 15) }} Guardar cuenta</button>
        </form>
    </div>

    <div class="table-card">
        @if ($items->isEmpty())
            <x-empty-state icon="sliders-horizontal" title="No hay ítems" message="Todavía no se registró ningún equipo o servicio." />
        @else
            <div class="table-wrap">
                <table class="data-table data-table-compact">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th class="is-numeric">Tarifa por hora</th>
                        <th>Estado</th>
                        <th class="is-numeric"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($items as $item)
                        <tr class="{{ $item->is_active ? '' : 'row-voided' }}">
                            <td class="cell-primary">{{ $item->name }}</td>
                            <td class="is-numeric cell-money cell-nowrap">{{ $item->default_hourly_rate ? format_money($item->default_hourly_rate) : 'Incluido' }}</td>
                            <td>
                                @if ($item->is_active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-neutral">Inactivo</span>
                                @endif
                            </td>
                            <td class="is-numeric">
                                <div class="row-actions">
                                    <a href="{{ route('rental-catalog-items.edit', $item) }}" class="btn btn-ghost btn-icon js-modal-link" data-modal-title="Editar ítem" title="Editar" aria-label="Editar">
                                        {{ icon('pencil', 'icon', 16) }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
