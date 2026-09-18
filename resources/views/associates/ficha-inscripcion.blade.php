@extends('layouts.app')

@section('title', 'Ficha de Inscripción — '.$associate->name)

@php
    $fmtDate = fn ($d) => $d ? $d->format('Y-m-d') : '';
    $val = fn (string $field) => old($field, $associate->{$field} ?? '');
    $complementary = old('complementary_activities', $associate->complementary_activities ?? []);
@endphp

@section('content')
    <x-page-header title="Generar Ficha de Inscripción" :subtitle="$associate->name">
        <x-slot:actions>
            <a href="{{ route('associates.show', $associate) }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <p class="cell-muted" style="max-width: 70ch; margin-top: 0;">
        Este formulario abre con los datos que ya tiene guardados el asociado. Al generar la ficha, los cambios que hagas aquí también se guardan en su registro — no es una copia aparte.
    </p>

    <form method="POST" action="{{ route('associates.inscripcion.update', $associate) }}" novalidate>
        @csrf
        @method('PUT')

        <fieldset class="form-section">
            <legend class="form-section-title">Datos de la empresa</legend>
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="field">
                        <label class="field-label" for="name">Razón Social <span class="required">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ $val('name') }}" required>
                        @error('name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="field">
                        <label class="field-label" for="ruc">RUC</label>
                        <input type="text" class="form-control @error('ruc') is-invalid @enderror" id="ruc" name="ruc" maxlength="11" inputmode="numeric" value="{{ $val('ruc') }}">
                        @error('ruc')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="field">
                        <label class="field-label" for="company">Nombre Comercial</label>
                        <input type="text" class="form-control" id="company" name="company" value="{{ $val('company') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="field">
                        <label class="field-label" for="activities_started_at">Fecha de inicio de Actividades</label>
                        <input type="date" class="form-control" id="activities_started_at" name="activities_started_at" value="{{ $fmtDate($associate->activities_started_at) }}">
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="field">
                        <label class="field-label" for="billing_address">Dirección</label>
                        <input type="text" class="form-control" id="billing_address" name="billing_address" value="{{ $val('billing_address') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="field">
                        <label class="field-label" for="billing_district">Distrito</label>
                        <input type="text" class="form-control" id="billing_district" name="billing_district" value="{{ $val('billing_district') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="billing_province">Provincia</label>
                        <input type="text" class="form-control" id="billing_province" name="billing_province" value="{{ $val('billing_province') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="mailing_address">Dirección para correspondencia</label>
                        <input type="text" class="form-control" id="mailing_address" name="mailing_address" value="{{ $val('mailing_address') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="field">
                        <label class="field-label" for="contact_phone">Teléfono</label>
                        <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="{{ $val('contact_phone') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="field">
                        <label class="field-label" for="email">E-mail</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ $val('email') }}">
                        @error('email')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="field">
                        <label class="field-label" for="website">Página web</label>
                        <input type="text" class="form-control" id="website" name="website" value="{{ $val('website') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="public_registry_entry">Registros Públicos — Partida Elect. N°</label>
                        <input type="text" class="form-control" id="public_registry_entry" name="public_registry_entry" value="{{ $val('public_registry_entry') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="public_registry_title">Título</label>
                        <input type="text" class="form-control" id="public_registry_title" name="public_registry_title" value="{{ $val('public_registry_title') }}">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="field">
                        <label class="field-label" for="notes">Observ.</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2">{{ $val('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="form-section">
            <legend class="form-section-title">Presentación de la empresa</legend>
            <div class="row g-3">
                <div class="col-12"><strong class="cell-muted" style="font-size: var(--text-xs); text-transform: uppercase;">Representante Legal</strong></div>
                <div class="col-md-5">
                    <div class="field"><label class="field-label" for="legal_rep_name">Nombre completo</label>
                        <input type="text" class="form-control" id="legal_rep_name" name="legal_rep_name" value="{{ $val('legal_rep_name') }}"></div>
                </div>
                <div class="col-md-3">
                    <div class="field"><label class="field-label" for="legal_rep_dni">DNI</label>
                        <input type="text" class="form-control" id="legal_rep_dni" name="legal_rep_dni" maxlength="20" value="{{ $val('legal_rep_dni') }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="field"><label class="field-label" for="legal_rep_position">Cargo</label>
                        <input type="text" class="form-control" id="legal_rep_position" name="legal_rep_position" value="{{ $val('legal_rep_position') }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="field"><label class="field-label" for="legal_rep_phone">Teléfono</label>
                        <input type="text" class="form-control" id="legal_rep_phone" name="legal_rep_phone" value="{{ $val('legal_rep_phone') }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="field"><label class="field-label" for="legal_rep_email">E-mail</label>
                        <input type="email" class="form-control" id="legal_rep_email" name="legal_rep_email" value="{{ $val('legal_rep_email') }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="field"><label class="field-label" for="legal_rep_birthday">Fecha de Nacimiento</label>
                        <input type="date" class="form-control" id="legal_rep_birthday" name="legal_rep_birthday" value="{{ $fmtDate($associate->legal_rep_birthday) }}"></div>
                </div>

                <div class="col-12" style="margin-top: var(--space-2);"><strong class="cell-muted" style="font-size: var(--text-xs); text-transform: uppercase;">Representante ante la Cámara</strong></div>
                <div class="col-md-5">
                    <div class="field"><label class="field-label" for="cch_rep_name">Nombre completo</label>
                        <input type="text" class="form-control" id="cch_rep_name" name="cch_rep_name" value="{{ $val('cch_rep_name') }}"></div>
                </div>
                <div class="col-md-3">
                    <div class="field"><label class="field-label" for="cch_rep_dni">DNI</label>
                        <input type="text" class="form-control" id="cch_rep_dni" name="cch_rep_dni" maxlength="20" value="{{ $val('cch_rep_dni') }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="field"><label class="field-label" for="cch_rep_position">Cargo</label>
                        <input type="text" class="form-control" id="cch_rep_position" name="cch_rep_position" value="{{ $val('cch_rep_position') }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="field"><label class="field-label" for="cch_rep_phone">Teléfono</label>
                        <input type="text" class="form-control" id="cch_rep_phone" name="cch_rep_phone" value="{{ $val('cch_rep_phone') }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="field"><label class="field-label" for="cch_rep_email">E-mail</label>
                        <input type="email" class="form-control" id="cch_rep_email" name="cch_rep_email" value="{{ $val('cch_rep_email') }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="field"><label class="field-label" for="cch_rep_birthday">Fecha de Nacimiento</label>
                        <input type="date" class="form-control" id="cch_rep_birthday" name="cch_rep_birthday" value="{{ $fmtDate($associate->cch_rep_birthday) }}"></div>
                </div>
            </div>
        </fieldset>

        <fieldset class="form-section">
            <legend class="form-section-title">Principales ejecutivos</legend>
            <table class="data-table data-table-compact" id="executives-table">
                <thead>
                <tr>
                    <th>Apellidos y Nombres</th>
                    <th>Cargo</th>
                    <th>Teléfono</th>
                    <th>Fecha de Nacimiento</th>
                    <th class="is-numeric"><span class="visually-hidden">Quitar</span></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($associate->executives as $i => $executive)
                    <tr>
                        <td><input type="text" class="form-control form-control-sm" name="executives[{{ $i }}][name]" value="{{ $executive->name }}"></td>
                        <td><input type="text" class="form-control form-control-sm" name="executives[{{ $i }}][position]" value="{{ $executive->position }}"></td>
                        <td><input type="text" class="form-control form-control-sm" name="executives[{{ $i }}][phone]" value="{{ $executive->phone }}"></td>
                        <td><input type="date" class="form-control form-control-sm" name="executives[{{ $i }}][birthday]" value="{{ $fmtDate($executive->birthday) }}"></td>
                        <td class="is-numeric"><button type="button" class="btn btn-ghost btn-icon js-remove-row" aria-label="Quitar fila">{{ icon('x', 'icon', 15) }}</button></td>
                    </tr>
                @empty
                @endforelse
                </tbody>
            </table>
            <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-executive">{{ icon('plus', 'icon', 15) }} Agregar ejecutivo</button>
            <template id="executive-row-template">
                <tr>
                    <td><input type="text" class="form-control form-control-sm" name="executives[__INDEX__][name]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="executives[__INDEX__][position]"></td>
                    <td><input type="text" class="form-control form-control-sm" name="executives[__INDEX__][phone]"></td>
                    <td><input type="date" class="form-control form-control-sm" name="executives[__INDEX__][birthday]"></td>
                    <td class="is-numeric"><button type="button" class="btn btn-ghost btn-icon js-remove-row" aria-label="Quitar fila">{{ icon('x', 'icon', 15) }}</button></td>
                </tr>
            </template>
        </fieldset>

        <fieldset class="form-section">
            <legend class="form-section-title">Actividad, sector y productos</legend>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="main_activity">Actividad principal <span class="field-help" style="display:inline;">(marque solo una)</span></label>
                        <select class="form-select" id="main_activity" name="main_activity">
                            <option value="">— Sin especificar —</option>
                            @foreach (\App\Models\Associate::ACTIVITY_OPTIONS as $option)
                                <option value="{{ $option }}" {{ $val('main_activity') === $option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label">Actividades complementarias <span class="field-help" style="display:inline;">(puede marcar más de una)</span></label>
                        <div class="d-flex gap-3 flex-wrap" style="padding-top: 6px;">
                            @foreach (\App\Models\Associate::ACTIVITY_OPTIONS as $option)
                                <label class="form-check" style="font-size: 0.875rem;">
                                    <input type="checkbox" class="form-check-input" name="complementary_activities[]" value="{{ $option }}" {{ in_array($option, (array) $complementary, true) ? 'checked' : '' }}>
                                    {{ $option }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field"><label class="field-label" for="ciiu">Código CIIU</label>
                        <input type="text" class="form-control" id="ciiu" name="ciiu" value="{{ $val('ciiu') }}"></div>
                </div>
                <div class="col-md-6">
                    <div class="field"><label class="field-label" for="profession">Profesión</label>
                        <input type="text" class="form-control" id="profession" name="profession" value="{{ $val('profession') }}"></div>
                </div>
            </div>

            <table class="data-table data-table-compact mt-3" id="products-table">
                <thead>
                <tr>
                    <th>Producto / Servicio</th>
                    <th class="is-numeric">F</th>
                    <th class="is-numeric">P</th>
                    <th class="is-numeric">C</th>
                    <th class="is-numeric">I</th>
                    <th class="is-numeric">S</th>
                    <th class="is-numeric">E</th>
                    <th class="is-numeric"><span class="visually-hidden">Quitar</span></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($associate->products as $i => $product)
                    <tr>
                        <td><input type="text" class="form-control form-control-sm" name="products[{{ $i }}][description]" value="{{ $product->description }}"></td>
                        @foreach (['is_fabrica', 'is_produce', 'is_comercializa', 'is_importa', 'is_servicios', 'is_exporta'] as $flag)
                            <td class="is-numeric"><input type="checkbox" class="form-check-input" name="products[{{ $i }}][{{ $flag }}]" value="1" {{ $product->{$flag} ? 'checked' : '' }}></td>
                        @endforeach
                        <td class="is-numeric"><button type="button" class="btn btn-ghost btn-icon js-remove-row" aria-label="Quitar fila">{{ icon('x', 'icon', 15) }}</button></td>
                    </tr>
                @empty
                @endforelse
                </tbody>
            </table>
            <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-product">{{ icon('plus', 'icon', 15) }} Agregar producto/servicio</button>
            <template id="product-row-template">
                <tr>
                    <td><input type="text" class="form-control form-control-sm" name="products[__INDEX__][description]"></td>
                    @foreach (['is_fabrica', 'is_produce', 'is_comercializa', 'is_importa', 'is_servicios', 'is_exporta'] as $flag)
                        <td class="is-numeric"><input type="checkbox" class="form-check-input" name="products[__INDEX__][{{ $flag }}]" value="1"></td>
                    @endforeach
                    <td class="is-numeric"><button type="button" class="btn btn-ghost btn-icon js-remove-row" aria-label="Quitar fila">{{ icon('x', 'icon', 15) }}</button></td>
                </tr>
            </template>
        </fieldset>

        <div class="d-flex gap-2" style="margin-top: var(--space-6);">
            <button type="submit" class="btn btn-primary">
                <span class="spinner"></span>
                <span class="btn-label-idle">{{ icon('file-down', 'icon', 16) }} Generar PDF</span>
            </button>
            <a href="{{ route('associates.show', $associate) }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            var nextIndex = { executives: {{ $associate->executives->count() }}, products: {{ $associate->products->count() }} };

            function addRow(tableId, templateId, key) {
                var table = document.getElementById(tableId).querySelector('tbody');
                var template = document.getElementById(templateId).innerHTML;
                var index = nextIndex[key]++;
                var row = document.createElement('tbody');
                row.innerHTML = template.replace(/__INDEX__/g, index);
                table.appendChild(row.firstElementChild);
            }

            document.getElementById('add-executive').addEventListener('click', function () {
                addRow('executives-table', 'executive-row-template', 'executives');
            });
            document.getElementById('add-product').addEventListener('click', function () {
                addRow('products-table', 'product-row-template', 'products');
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
