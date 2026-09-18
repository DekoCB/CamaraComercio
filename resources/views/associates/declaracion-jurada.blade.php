@extends('layouts.app')

@section('title', 'Declaración Jurada — '.$associate->name)

@php
    $val = fn (string $field) => old($field, $associate->{$field} ?? '');
@endphp

@section('content')
    <x-page-header title="Generar Declaración Jurada" :subtitle="$associate->name">
        <x-slot:actions>
            <a href="{{ route('associates.show', $associate) }}" class="btn btn-secondary btn-sm">
                {{ icon('arrow-left', 'icon', 16) }} Volver
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="voided-panel mb-3" style="align-items: flex-start;">
        {{ icon('info', 'icon', 22) }}
        <div>
            <strong>Este documento se genera para imprimir y firmar</strong>
            <div class="cell-muted" style="font-size: var(--text-xs);">
                La Declaración Jurada requiere firma física del declarante — el PDF sale con los recuadros de firma y huella digital en blanco, salvo que subas una imagen escaneada nítida de cada una más abajo (ambas opcionales).
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('associates.declaracion.update', $associate) }}" enctype="multipart/form-data" novalidate>
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
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="billing_address">Domicilio</label>
                        <input type="text" class="form-control" id="billing_address" name="billing_address" value="{{ $val('billing_address') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="field">
                        <label class="field-label" for="billing_district">Distrito</label>
                        <input type="text" class="form-control" id="billing_district" name="billing_district" value="{{ $val('billing_district') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="field">
                        <label class="field-label" for="billing_province">Provincia</label>
                        <input type="text" class="form-control" id="billing_province" name="billing_province" value="{{ $val('billing_province') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="field">
                        <label class="field-label" for="billing_department">Departamento</label>
                        <input type="text" class="form-control" id="billing_department" name="billing_department" value="{{ $val('billing_department') }}">
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="form-section">
            <legend class="form-section-title">Quién declara</legend>
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="field">
                        <label class="field-label" for="legal_rep_name">Nombre completo <span class="required">*</span></label>
                        <input type="text" class="form-control @error('legal_rep_name') is-invalid @enderror" id="legal_rep_name" name="legal_rep_name" value="{{ $val('legal_rep_name') }}" required>
                        <div class="field-help">
                            {{ $associate->person_type === 'PERSONA NATURAL' ? 'El propio asociado, como persona natural.' : 'El representante legal, actuando en nombre de la empresa.' }}
                        </div>
                        @error('legal_rep_name')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="field">
                        <label class="field-label" for="legal_rep_dni">DNI N°</label>
                        <input type="text" class="form-control" id="legal_rep_dni" name="legal_rep_dni" maxlength="20" value="{{ $val('legal_rep_dni') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="membership_status">Declara en calidad de <span class="required">*</span></label>
                        <select class="form-select @error('membership_status') is-invalid @enderror" id="membership_status" name="membership_status" required>
                            @foreach (\App\Models\Associate::MEMBERSHIP_DECLARATION_OPTIONS as $option)
                                <option value="{{ $option }}" {{ old('membership_status') === $option ? 'selected' : '' }}>{{ ucfirst(strtolower($option)) }}</option>
                            @endforeach
                        </select>
                        @error('membership_status')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="declaration_date">Fecha de la declaración <span class="required">*</span></label>
                        <input type="date" class="form-control @error('declaration_date') is-invalid @enderror" id="declaration_date" name="declaration_date" value="{{ old('declaration_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                        @error('declaration_date')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="form-section">
            <legend class="form-section-title">Firma y huella digital (opcional)</legend>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="signature">Firma escaneada</label>
                        <input type="file" class="form-control @error('signature') is-invalid @enderror" id="signature" name="signature" accept="image/*">
                        <div class="field-help">JPG o PNG, máximo 10 MB. Si no se sube, el PDF queda con el recuadro en blanco.</div>
                        @error('signature')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label class="field-label" for="fingerprint">Huella digital escaneada</label>
                        <input type="file" class="form-control @error('fingerprint') is-invalid @enderror" id="fingerprint" name="fingerprint" accept="image/*">
                        <div class="field-help">JPG o PNG, máximo 10 MB. Si no se sube, el PDF queda con el recuadro en blanco.</div>
                        @error('fingerprint')<div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
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
