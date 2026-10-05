@php
    $val = fn (string $field, $default = '') => old($field, isset($rental) ? $rental->{$field} : $default);

    // Para precargar cada fila del catálogo con lo que ya tiene guardado
    // este alquiler (si lo hay) — una fila por ítem del catálogo, por id.
    $existingByCatalog = isset($rental)
        ? $rental->lineItems->whereNotNull('catalog_item_id')->keyBy('catalog_item_id')
        : collect();
    $customItems = isset($rental) ? $rental->lineItems->whereNull('catalog_item_id')->values() : collect();
    $oldLineItems = old('line_items');

    $catering = isset($rental) ? $rental->catering : null;
    $oldCatering = old('catering');
@endphp

<form method="POST" action="{{ isset($rental) ? route('rentals.update', $rental) : route('rentals.store') }}" class="js-rental-form" novalidate>
    @csrf
    @if (isset($rental))
        @method('PUT')
    @endif

    <fieldset class="form-section">
        <legend class="form-section-title">Datos del evento</legend>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="field">
                    <label class="field-label" for="space_id">Espacio <span class="required">*</span></label>
                    <select class="form-select @error('space_id') is-invalid @enderror" id="space_id" name="space_id" required>
                        <option value="">— Selecciona —</option>
                        @foreach ($spaces as $space)
                            <option value="{{ $space->id }}" data-rate="{{ $space->default_rate ?? 0 }}" {{ (string) $val('space_id') === (string) $space->id ? 'selected' : '' }}>{{ $space->name }}</option>
                        @endforeach
                    </select>
                    @error('space_id')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-3">
                <div class="field">
                    <label class="field-label" for="associate_id">Asociado</label>
                    <select class="form-select @error('associate_id') is-invalid @enderror" id="associate_id" name="associate_id">
                        <option value="">— No es un asociado —</option>
                        @foreach ($associates as $associate)
                            <option value="{{ $associate->id }}" {{ (string) $val('associate_id') === (string) $associate->id ? 'selected' : '' }}>{{ $associate->name }}</option>
                        @endforeach
                    </select>
                    @error('associate_id')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-3">
                <div class="field">
                    <label class="field-label" for="client_name">Nombre del cliente</label>
                    <input type="text" class="form-control @error('client_name') is-invalid @enderror" id="client_name" name="client_name" maxlength="150" value="{{ $val('client_name') }}" placeholder="Si no es asociado">
                    <div class="field-help">Así aparece en la cotización ("Sres. ___").</div>
                    @error('client_name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="field">
                    <label class="field-label" for="starts_at">Inicio <span class="required">*</span></label>
                    <input type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" name="starts_at" required
                           value="{{ old('starts_at', isset($rental) ? $rental->starts_at->format('Y-m-d\TH:i') : '') }}">
                    @error('starts_at')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="field">
                    <label class="field-label" for="ends_at">Fin <span class="required">*</span></label>
                    <input type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" name="ends_at" required
                           value="{{ old('ends_at', isset($rental) ? $rental->ends_at->format('Y-m-d\TH:i') : '') }}">
                    @error('ends_at')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="field">
                    <label class="field-label" for="purpose">Evento / motivo</label>
                    <input type="text" class="form-control @error('purpose') is-invalid @enderror" id="purpose" name="purpose" maxlength="255" value="{{ $val('purpose') }}" placeholder="Capacitación, charla...">
                    @error('purpose')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend class="form-section-title">Bienes y servicios de la CCH</legend>
        <div class="table-wrap">
            <table class="data-table data-table-compact" id="lineItemsTable">
                <thead>
                <tr>
                    <th>Ítem</th>
                    <th class="is-numeric" style="width:110px;">Cant. / horas</th>
                    <th class="is-numeric" style="width:140px;">Precio/hora</th>
                    <th class="is-numeric" style="width:110px;">Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($catalogItems as $i => $catalogItem)
                    @php
                        $rowOld = $oldLineItems[$i] ?? null;
                        $existing = $existingByCatalog->get($catalogItem->id);
                        $qty = $rowOld['quantity'] ?? $existing?->quantity ?? '';
                        $rate = $rowOld['hourly_rate'] ?? $existing?->hourly_rate ?? $catalogItem->default_hourly_rate;
                    @endphp
                    <tr>
                        <td class="cell-primary">
                            {{ $catalogItem->name }}
                            <input type="hidden" name="line_items[{{ $i }}][catalog_item_id]" value="{{ $catalogItem->id }}">
                        </td>
                        <td class="is-numeric">
                            <input type="number" min="0" step="0.5" class="form-control form-control-sm js-line-qty" name="line_items[{{ $i }}][quantity]" value="{{ $qty }}">
                        </td>
                        <td class="is-numeric">
                            <div class="input-money">
                                <span class="currency-prefix">S/</span>
                                <input type="number" min="0" step="0.01" class="form-control form-control-sm js-line-rate" name="line_items[{{ $i }}][hourly_rate]" value="{{ $rate }}">
                            </div>
                        </td>
                        <td class="is-numeric cell-money js-line-total">—</td>
                    </tr>
                @endforeach
                @for ($j = 0; $j < 3; $j++)
                    @php
                        $idx = $catalogItems->count() + $j;
                        $custom = $customItems->get($j);
                        $rowOld = $oldLineItems[$idx] ?? null;
                    @endphp
                    <tr>
                        <td>
                            <input type="text" class="form-control form-control-sm" name="line_items[{{ $idx }}][description]" placeholder="Otro ítem..." maxlength="150" value="{{ $rowOld['description'] ?? $custom?->description }}">
                        </td>
                        <td class="is-numeric">
                            <input type="number" min="0" step="0.5" class="form-control form-control-sm js-line-qty" name="line_items[{{ $idx }}][quantity]" value="{{ $rowOld['quantity'] ?? $custom?->quantity }}">
                        </td>
                        <td class="is-numeric">
                            <div class="input-money">
                                <span class="currency-prefix">S/</span>
                                <input type="number" min="0" step="0.01" class="form-control form-control-sm js-line-rate" name="line_items[{{ $idx }}][hourly_rate]" value="{{ $rowOld['hourly_rate'] ?? $custom?->hourly_rate }}">
                            </div>
                        </td>
                        <td class="is-numeric cell-money js-line-total">—</td>
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>
        <p class="cell-muted" style="font-size: var(--text-xs); margin: 0;">Deja la cantidad en 0 (o vacía) para los ítems que no se piden. La mayoría se incluye sin costo — solo escribe un precio si corresponde cobrarlo aparte.</p>
    </fieldset>

    <fieldset class="form-section">
        <legend class="form-section-title">Coffee break (opcional)</legend>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="field">
                    <label class="field-label" for="catering_people_count">N° de personas</label>
                    <input type="number" min="0" step="1" class="form-control @error('catering.people_count') is-invalid @enderror" id="catering_people_count" name="catering[people_count]" value="{{ old('catering.people_count', $catering?->people_count) }}">
                    @error('catering.people_count')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-3">
                <div class="field">
                    <label class="field-label" for="catering_drink_option">Bebida</label>
                    <select class="form-select @error('catering.drink_option') is-invalid @enderror" id="catering_drink_option" name="catering[drink_option]">
                        <option value="">— Ninguna —</option>
                        @foreach (\App\Models\RentalCatering::DRINK_OPTIONS as $option)
                            <option value="{{ $option }}" {{ old('catering.drink_option', $catering?->drink_option) === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="field">
                    <label class="field-label" for="catering_sandwich_option">Sándwich</label>
                    <select class="form-select @error('catering.sandwich_option') is-invalid @enderror" id="catering_sandwich_option" name="catering[sandwich_option]">
                        <option value="">— Ninguno —</option>
                        @foreach (\App\Models\RentalCatering::SANDWICH_OPTIONS as $option)
                            <option value="{{ $option }}" {{ old('catering.sandwich_option', $catering?->sandwich_option) === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="field">
                    <label class="field-label" for="catering_dessert_option">Complemento dulce</label>
                    <select class="form-select @error('catering.dessert_option') is-invalid @enderror" id="catering_dessert_option" name="catering[dessert_option]">
                        <option value="">— Ninguno —</option>
                        @foreach (\App\Models\RentalCatering::DESSERT_OPTIONS as $option)
                            <option value="{{ $option }}" {{ old('catering.dessert_option', $catering?->dessert_option) === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="field">
                    <label class="field-label" for="catering_daily_cost">Costo por día (incluye IGV)</label>
                    <div class="input-money">
                        <span class="currency-prefix">S/</span>
                        <input type="number" min="0" step="0.01" class="form-control @error('catering.daily_cost') is-invalid @enderror" id="catering_daily_cost" name="catering[daily_cost]" value="{{ old('catering.daily_cost', $catering?->daily_cost) }}">
                    </div>
                    @error('catering.daily_cost')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-8">
                <div class="field">
                    <label class="field-label" for="catering_notes">Notas del coffee break</label>
                    <input type="text" class="form-control @error('catering.notes') is-invalid @enderror" id="catering_notes" name="catering[notes]" maxlength="500" value="{{ old('catering.notes', $catering?->notes) }}">
                    @error('catering.notes')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend class="form-section-title">Monto y pago</legend>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="field">
                    <label class="field-label" for="amount">Monto total a cobrar <span class="required">*</span></label>
                    <div class="input-money">
                        <span class="currency-prefix">S/</span>
                        <input type="number" step="0.01" min="0" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" required value="{{ $val('amount') }}">
                    </div>
                    <div class="field-help" id="suggestedAmountHint" hidden>
                        Suma de los ítems marcados + coffee break: <span id="suggestedAmountValue"></span>
                        <button type="button" class="btn btn-link btn-sm" id="useSuggestedAmount" style="padding: 0; vertical-align: baseline;">Usar</button>
                    </div>
                    @error('amount')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-6">
                <div class="field">
                    <label class="field-label" for="bank_account">Cuenta bancaria para este pago</label>
                    <textarea class="form-control @error('bank_account') is-invalid @enderror" id="bank_account" name="bank_account" rows="2" maxlength="255">{{ $val('bank_account') ?: (isset($rental) ? '' : $bankAccountDefault) }}</textarea>
                    <div class="field-help">Precargada con la cuenta oficial — edítala solo si este pago va a una distinta.</div>
                    @error('bank_account')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-12">
                <div class="field">
                    <label class="field-label" for="notes">Notas internas</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" maxlength="1000">{{ $val('notes') }}</textarea>
                    @error('notes')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </fieldset>

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} {{ isset($rental) ? 'Guardar cambios' : 'Crear cotización' }}</span>
        </button>
        <a href="{{ isset($rental) ? route('rentals.show', $rental) : route('rentals.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
