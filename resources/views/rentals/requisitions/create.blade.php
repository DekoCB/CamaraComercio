@extends('layouts.app')

@section('title', 'Nuevo requerimiento de pago')

@section('content')
    <x-page-header title="Nuevo requerimiento de pago">
        <x-slot:actions>
            <a href="{{ route('rentals.requisitions.index') }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface" style="max-width: 920px">
        <form method="POST" action="{{ route('rentals.requisitions.store') }}" novalidate>
            @csrf

            <fieldset class="form-section">
                <legend class="form-section-title">Datos del requerimiento</legend>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="field">
                            <label class="field-label" for="type">Tipo <span class="required">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="">— Selecciona —</option>
                                @foreach (\App\Models\PaymentRequisition::TYPES as $key => $label)
                                    <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="field">
                            <label class="field-label" for="issued_at">Fecha <span class="required">*</span></label>
                            <input type="date" class="form-control @error('issued_at') is-invalid @enderror" id="issued_at" name="issued_at" required
                                   value="{{ old('issued_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                            @error('issued_at')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="field">
                            <label class="field-label" for="requester_area">Área que solicita</label>
                            <input type="text" class="form-control" id="requester_area" name="requester_area" maxlength="150" value="{{ old('requester_area', 'Logística y Operaciones') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="field">
                            <label class="field-label" for="recipient_name">Dirigido a <span class="required">*</span></label>
                            <input type="text" class="form-control @error('recipient_name') is-invalid @enderror" id="recipient_name" name="recipient_name" required maxlength="150"
                                   value="{{ old('recipient_name', $defaultRecipientName) }}">
                            @error('recipient_name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field">
                            <label class="field-label" for="recipient_role">Cargo del destinatario</label>
                            <input type="text" class="form-control" id="recipient_role" name="recipient_role" maxlength="150" value="{{ old('recipient_role', $defaultRecipientRole) }}">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="field">
                            <label class="field-label" for="subject">Asunto <span class="required">*</span></label>
                            <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" required maxlength="255" value="{{ old('subject') }}">
                            @error('subject')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title">Beneficiario y datos de pago</legend>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="field">
                            <label class="field-label" for="beneficiary_name">Titular <span class="required">*</span></label>
                            <input type="text" class="form-control @error('beneficiary_name') is-invalid @enderror" id="beneficiary_name" name="beneficiary_name" required maxlength="150" value="{{ old('beneficiary_name') }}">
                            <div class="field-help">Quien recibe el desembolso — el trabajador en un reembolso, el proveedor en un pago.</div>
                            @error('beneficiary_name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field">
                            <label class="field-label" for="provider_ruc">RUC del proveedor (si aplica)</label>
                            <input type="text" class="form-control" id="provider_ruc" name="provider_ruc" maxlength="20" value="{{ old('provider_ruc') }}">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="field">
                            <label class="field-label" for="bank_details">Datos bancarios</label>
                            <textarea class="form-control" id="bank_details" name="bank_details" rows="3" maxlength="1000" placeholder="Banco: ...&#10;Cuenta Soles: ...&#10;CCI: ...">{{ old('bank_details') }}</textarea>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title">Ítems</legend>
                <div class="table-wrap">
                    <table class="data-table data-table-compact" id="items-table">
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>N° comprobante/operación</th>
                            <th>Descripción</th>
                            <th class="is-numeric">Cant.</th>
                            <th class="is-numeric">Pr. unitario</th>
                            <th class="is-numeric">Importe</th>
                            <th class="is-numeric"><span class="visually-hidden">Quitar</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        @for ($i = 0; $i < 3; $i++)
                            <tr>
                                <td><input type="date" class="form-control form-control-sm" name="items[{{ $i }}][item_date]"></td>
                                <td><input type="text" class="form-control form-control-sm" name="items[{{ $i }}][reference]" maxlength="40"></td>
                                <td><input type="text" class="form-control form-control-sm" name="items[{{ $i }}][description]" maxlength="255"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[{{ $i }}][quantity]"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[{{ $i }}][unit_price]"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[{{ $i }}][amount]"></td>
                                <td class="is-numeric"><button type="button" class="btn btn-ghost btn-icon js-remove-row" aria-label="Quitar fila">{{ icon('x', 'icon', 15) }}</button></td>
                            </tr>
                        @endfor
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-item">{{ icon('plus', 'icon', 15) }} Agregar ítem</button>
                <template id="item-row-template">
                    <tr>
                        <td><input type="date" class="form-control form-control-sm" name="items[__INDEX__][item_date]"></td>
                        <td><input type="text" class="form-control form-control-sm" name="items[__INDEX__][reference]" maxlength="40"></td>
                        <td><input type="text" class="form-control form-control-sm" name="items[__INDEX__][description]" maxlength="255"></td>
                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[__INDEX__][quantity]"></td>
                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[__INDEX__][unit_price]"></td>
                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[__INDEX__][amount]"></td>
                        <td class="is-numeric"><button type="button" class="btn btn-ghost btn-icon js-remove-row" aria-label="Quitar fila">{{ icon('x', 'icon', 15) }}</button></td>
                    </tr>
                </template>
                @error('items')<div class="field-error mt-2">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </fieldset>

            <fieldset class="form-section">
                <legend class="form-section-title">Notas</legend>
                <textarea class="form-control" name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
            </fieldset>

            <div class="d-flex gap-2" style="margin-top: var(--space-6);">
                <button type="submit" class="btn btn-primary">
                    <span class="spinner"></span>
                    <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} Registrar</span>
                </button>
                <a href="{{ route('rentals.requisitions.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var nextIndex = 3;

            document.getElementById('add-item').addEventListener('click', function () {
                var table = document.getElementById('items-table').querySelector('tbody');
                var template = document.getElementById('item-row-template').innerHTML;
                var row = document.createElement('tbody');
                row.innerHTML = template.replace(/__INDEX__/g, nextIndex++);
                table.appendChild(row.firstElementChild);
            });

            document.body.addEventListener('click', function (event) {
                var button = event.target.closest('.js-remove-row');
                if (button) {
                    button.closest('tr').remove();
                }
            });
        })();
    </script>
@endpush
