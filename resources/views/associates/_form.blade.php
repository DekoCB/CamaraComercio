@php
    use App\Models\Associate;

    // Field definitions mirror the columns of the Cámara's master Excel
    // ("DATA DE ASOCIADOS", C–AJ + OBSERVACIONES), grouped the way the
    // sheet reads left to right. Each entry: [name, label, type, extra].
    $sections = [
        'Datos generales' => [
            ['name', 'Razón social', 'text', ['required' => true, 'col' => 8]],
            ['ruc', 'RUC', 'text', ['maxlength' => 11, 'inputmode' => 'numeric', 'help' => 'Opcional. 11 dígitos.', 'col' => 4]],
            ['company', 'Nombre comercial', 'text', ['col' => 8]],
            ['status', 'Estado', 'select', ['options' => Associate::STATUSES, 'col' => 4]],
            ['person_type', 'Tipo de persona', 'select', ['options' => Associate::PERSON_TYPES, 'placeholder' => true, 'col' => 4]],
            ['sectorista', 'Sectorista', 'text', ['col' => 4]],
            ['category', 'Categoría', 'text', ['maxlength' => 10, 'col' => 4]],
            ['monthly_fee', 'Monto a pagar (S/)', 'number', ['step' => '0.01', 'min' => '0', 'help' => 'Cuota mensual propia. Si se deja vacío se usa el monto indicado al generar facturas.', 'col' => 4]],
            ['joined_at', 'Fecha de ingreso', 'date', ['col' => 4]],
            ['activities_started_at', 'Fecha de inicio de actividades', 'date', ['col' => 4]],
            ['anniversary_date', 'Fecha de aniversario', 'date', ['col' => 4]],
            ['email', 'Correo de la empresa', 'email', ['col' => 6]],
            ['contact_phone', 'Teléfono de la empresa', 'text', ['col' => 6]],
            ['website', 'Página web', 'text', ['col' => 12]],
        ],
        'Direcciones' => [
            ['billing_address', 'Dirección de facturación', 'text', ['col' => 8]],
            ['billing_district', 'Distrito', 'text', ['col' => 4]],
            ['billing_province', 'Provincia', 'text', ['col' => 6]],
            ['mailing_address', 'Dirección de correspondencia', 'text', ['col' => 8]],
            ['mailing_district', 'Distrito de correspondencia', 'text', ['col' => 4]],
        ],
        'Clasificación' => [
            ['company_size', 'Según su tamaño', 'text', ['suggestions' => Associate::COMPANY_SIZES, 'col' => 6]],
            ['activity_type', 'Según su actividad', 'text', ['suggestions' => Associate::ACTIVITY_TYPES, 'col' => 6]],
            ['sector_committee', 'Comité sectorial', 'text', ['col' => 6]],
            ['ciiu', 'CIIU', 'text', ['col' => 6]],
            ['profession', 'Profesión', 'text', ['col' => 6]],
            ['sub_sector', 'Sub sector', 'textarea', ['rows' => 2, 'col' => 12]],
            ['public_registry_entry', 'Registros Públicos — Partida Elect. N°', 'text', ['col' => 6]],
            ['public_registry_title', 'Registros Públicos — Título', 'text', ['col' => 6]],
            ['main_activity', 'Actividad principal', 'select', ['options' => Associate::ACTIVITY_OPTIONS, 'placeholder' => true, 'col' => 6, 'help' => 'Para la Ficha de Inscripción — marque solo una.']],
            ['complementary_activities', 'Actividades complementarias', 'checkboxes', ['options' => Associate::ACTIVITY_OPTIONS, 'col' => 6, 'help' => 'Puede marcar más de una.']],
        ],
        'Representante legal' => [
            ['legal_rep_name', 'Nombre completo', 'text', ['col' => 8]],
            ['legal_rep_dni', 'DNI N°', 'text', ['maxlength' => 20, 'col' => 4]],
            ['legal_rep_gender', 'Género', 'select', ['options' => Associate::GENDERS, 'placeholder' => true, 'col' => 4]],
            ['legal_rep_birthday', 'Cumpleaños', 'date', ['col' => 4]],
            ['legal_rep_position', 'Cargo', 'text', ['col' => 4]],
            ['legal_rep_phone', 'Celular', 'text', ['col' => 4]],
            ['legal_rep_email', 'Correo', 'email', ['col' => 12]],
        ],
        'Representante ante la CCH' => [
            ['cch_rep_name', 'Nombre completo', 'text', ['col' => 8]],
            ['cch_rep_dni', 'DNI N°', 'text', ['maxlength' => 20, 'col' => 4]],
            ['cch_rep_gender', 'Género', 'select', ['options' => Associate::GENDERS, 'placeholder' => true, 'col' => 4]],
            ['cch_rep_birthday', 'Cumpleaños', 'date', ['col' => 4]],
            ['cch_rep_position', 'Cargo', 'text', ['col' => 4]],
            ['cch_rep_phone', 'Celular', 'text', ['col' => 4]],
            ['cch_rep_email', 'Correo', 'email', ['col' => 12]],
        ],
    ];

    $value = function (string $field, string $type) use ($associate) {
        $current = $associate?->{$field};
        if ($type === 'checkboxes') {
            return old($field, $current ?? []);
        }
        if ($type === 'date' && $current) {
            $current = $current->format('Y-m-d');
        }

        return old($field, $current ?? '');
    };
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" novalidate class="associate-form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @foreach ($sections as $title => $fields)
        <fieldset class="form-section">
            <legend class="form-section-title">{{ $title }}</legend>
            <div class="row g-3">
                @foreach ($fields as [$field, $label, $type, $extra])
                    <div class="col-md-{{ $extra['col'] ?? 6 }}">
                        <div class="field">
                            <label class="field-label" for="{{ $field }}">
                                {{ $label }}
                                @if ($extra['required'] ?? false)
                                    <span class="required">*</span>
                                @endif
                            </label>

                            @if ($type === 'select')
                                <select class="form-select @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}">
                                    @if ($extra['placeholder'] ?? false)
                                        <option value="">— Sin especificar —</option>
                                    @endif
                                    @foreach ($extra['options'] as $option)
                                        <option value="{{ $option }}" {{ (string) $value($field, $type) === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif ($type === 'textarea')
                                <textarea class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}"
                                          rows="{{ $extra['rows'] ?? 3 }}">{{ $value($field, $type) }}</textarea>
                            @elseif ($type === 'checkboxes')
                                @php $selected = (array) $value($field, $type); @endphp
                                <div class="d-flex gap-3 flex-wrap" style="padding-top: 6px;">
                                    @foreach ($extra['options'] as $option)
                                        <label class="form-check" style="font-size: 0.875rem;">
                                            <input type="checkbox" class="form-check-input" name="{{ $field }}[]" value="{{ $option }}" {{ in_array($option, $selected, true) ? 'checked' : '' }}>
                                            {{ $option }}
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <input type="{{ $type }}" class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}"
                                       value="{{ $value($field, $type) }}"
                                       @if ($extra['required'] ?? false) required @endif
                                       @isset($extra['maxlength']) maxlength="{{ $extra['maxlength'] }}" @endisset
                                       @isset($extra['inputmode']) inputmode="{{ $extra['inputmode'] }}" @endisset
                                       @isset($extra['step']) step="{{ $extra['step'] }}" @endisset
                                       @isset($extra['min']) min="{{ $extra['min'] }}" @endisset
                                       @isset($extra['suggestions']) list="{{ $field }}-suggestions" @endisset>
                                @isset($extra['suggestions'])
                                    <datalist id="{{ $field }}-suggestions">
                                        @foreach ($extra['suggestions'] as $option)
                                            <option value="{{ $option }}"></option>
                                        @endforeach
                                    </datalist>
                                @endisset
                            @endif

                            @isset($extra['help'])
                                <div class="field-help">{{ $extra['help'] }}</div>
                            @endisset
                            @error($field)
                                <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                @endforeach
            </div>
        </fieldset>
    @endforeach

    <fieldset class="form-section">
        <legend class="form-section-title">Imagen y observaciones</legend>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="field">
                    <label class="field-label" for="image">Imagen (logo o foto)</label>
                    @if ($associate?->image_path)
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="{{ $associate->imageUrl() }}" alt="" style="width: 56px; height: 56px; object-fit: cover; border-radius: var(--radius-md);">
                            <label class="form-check" style="font-size: 0.875rem;">
                                <input type="checkbox" class="form-check-input" name="remove_image" value="1" {{ old('remove_image') ? 'checked' : '' }}>
                                Quitar imagen actual
                            </label>
                        </div>
                    @endif
                    <input type="file" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/*">
                    <div class="field-help">JPG o PNG, máximo 10 MB.</div>
                    @error('image')
                        <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-md-6">
                <div class="field">
                    <label class="field-label" for="notes">Observaciones</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="4">{{ old('notes', $associate->notes ?? '') }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </fieldset>

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('save', 'icon', 16) }} Guardar</span>
        </button>
        <a href="{{ $associate ? route('associates.show', $associate) : route('associates.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
