@php
    $val = fn (string $field, $default = '') => old($field, isset($session) ? $session->{$field} : $default);
@endphp

<form method="POST" action="{{ isset($session) ? route('parking.update', $session) : route('parking.store') }}" novalidate>
    @csrf
    @if (isset($session))
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-md-5">
            <div class="field">
                <label class="field-label" for="plate">Placa <span class="required">*</span></label>
                <input type="text" class="form-control @error('plate') is-invalid @enderror" id="plate" name="plate" required maxlength="10" style="text-transform: uppercase;" value="{{ $val('plate') }}">
                @error('plate')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-7">
            <div class="field">
                <label class="field-label" for="associate_id">Asociado dueño (si aplica)</label>
                <select class="form-select @error('associate_id') is-invalid @enderror" id="associate_id" name="associate_id">
                    <option value="">— No es de un asociado —</option>
                    @foreach ($associates as $associate)
                        <option value="{{ $associate->id }}" {{ (string) $val('associate_id') === (string) $associate->id ? 'selected' : '' }}>{{ $associate->name }}</option>
                    @endforeach
                </select>
                @error('associate_id')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="owner_name">Nombre del dueño</label>
                <input type="text" class="form-control @error('owner_name') is-invalid @enderror" id="owner_name" name="owner_name" maxlength="150" value="{{ $val('owner_name') }}" placeholder="Si no es asociado">
                <div class="field-help">Obligatorio solo si el vehículo no es de un asociado.</div>
                @error('owner_name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="vehicle_description">Vehículo</label>
                <input type="text" class="form-control @error('vehicle_description') is-invalid @enderror" id="vehicle_description" name="vehicle_description" maxlength="150" value="{{ $val('vehicle_description') }}" placeholder="Modelo, color...">
                @error('vehicle_description')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="entered_at">Hora de entrada <span class="required">*</span></label>
                <input type="datetime-local" class="form-control @error('entered_at') is-invalid @enderror" id="entered_at" name="entered_at" required
                       value="{{ old('entered_at', isset($session) ? $session->entered_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
                @error('entered_at')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="field">
                <label class="field-label" for="amount">Monto a cobrar</label>
                <div class="input-money">
                    <span class="currency-prefix">S/</span>
                    <input type="number" step="0.01" min="0" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ $val('amount') }}">
                </div>
                <div class="field-help">Opcional — se puede dejar o ajustar también al registrar la salida.</div>
                @error('amount')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-12">
            <div class="field">
                <label class="field-label" for="notes">Notas</label>
                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" maxlength="1000">{{ $val('notes') }}</textarea>
                @error('notes')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="d-flex gap-2" style="margin-top: var(--space-6);">
        <button type="submit" class="btn btn-primary">
            <span class="spinner"></span>
            <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} {{ isset($session) ? 'Guardar cambios' : 'Registrar entrada' }}</span>
        </button>
        <a href="{{ route('parking.index') }}" class="btn btn-secondary js-modal-cancel">Cancelar</a>
    </div>
</form>
