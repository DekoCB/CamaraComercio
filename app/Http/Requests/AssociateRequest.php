<?php

namespace App\Http\Requests;

use App\Models\Associate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssociateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('associates.manage');
    }

    protected function prepareForValidation(): void
    {
        // Text fields the master Excel keeps in upper case; blank strings
        // become NULL so "empty" is stored consistently across manual
        // entry and the importer.
        $data = [];
        foreach ($this->all() as $key => $value) {
            $data[$key] = is_string($value) && trim($value) === '' ? null : $value;
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $ignore = $this->route('associate');

        return [
            // Razón social
            'name' => ['required', 'string', 'max:150'],
            'status' => ['sometimes', 'required', Rule::in(Associate::STATUSES)],
            'sectorista' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:10'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'joined_at' => ['nullable', 'date'],
            'person_type' => ['nullable', Rule::in(Associate::PERSON_TYPES)],
            'anniversary_date' => ['nullable', 'date'],
            // OPEN_BUSINESS_DECISIONS.md #12: RUC adopted as the associate's
            // legal identifier, but optional — not every existing/new
            // associate has it on hand at registration time. 11 digits per
            // SUNAT (Peru) RUC format.
            'ruc' => ['nullable', 'digits:11', Rule::unique('associates', 'ruc')->ignore($ignore)],
            // Nombre comercial
            'company' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            // Not a full identifier-uniqueness rule (docs/OPEN_BUSINESS_DECISIONS.md
            // #12 is still open) — just closing a real inconsistency the audit
            // found: the Excel importer already rejects a duplicate email
            // (AssociateImportService), but manual create/edit didn't, so the
            // same associate could be entered twice by hand. `email` stays
            // nullable, and Laravel's unique rule already excludes NULL rows,
            // so associates without an email never collide with each other.
            'email' => ['nullable', 'email', 'max:190', Rule::unique('associates', 'email')->ignore($ignore)],

            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_district' => ['nullable', 'string', 'max:100'],
            'billing_province' => ['nullable', 'string', 'max:100'],
            'mailing_address' => ['nullable', 'string', 'max:255'],
            'mailing_district' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:190'],

            'company_size' => ['nullable', 'string', 'max:50'],
            'activity_type' => ['nullable', 'string', 'max:50'],
            'sector_committee' => ['nullable', 'string', 'max:150'],
            'ciiu' => ['nullable', 'string', 'max:255'],
            'sub_sector' => ['nullable', 'string', 'max:2000'],
            'profession' => ['nullable', 'string', 'max:100'],

            'activities_started_at' => ['nullable', 'date'],
            'public_registry_entry' => ['nullable', 'string', 'max:100'],
            'public_registry_title' => ['nullable', 'string', 'max:100'],
            'main_activity' => ['nullable', Rule::in(Associate::ACTIVITY_OPTIONS)],
            'complementary_activities' => ['nullable', 'array'],
            'complementary_activities.*' => [Rule::in(Associate::ACTIVITY_OPTIONS)],

            'legal_rep_name' => ['nullable', 'string', 'max:150'],
            'legal_rep_dni' => ['nullable', 'string', 'max:20'],
            'legal_rep_gender' => ['nullable', Rule::in(Associate::GENDERS)],
            'legal_rep_birthday' => ['nullable', 'date'],
            'legal_rep_phone' => ['nullable', 'string', 'max:40'],
            'legal_rep_email' => ['nullable', 'email', 'max:190'],
            'legal_rep_position' => ['nullable', 'string', 'max:100'],

            'cch_rep_name' => ['nullable', 'string', 'max:150'],
            'cch_rep_dni' => ['nullable', 'string', 'max:20'],
            'cch_rep_gender' => ['nullable', Rule::in(Associate::GENDERS)],
            'cch_rep_birthday' => ['nullable', 'date'],
            'cch_rep_phone' => ['nullable', 'string', 'max:40'],
            'cch_rep_email' => ['nullable', 'email', 'max:190'],
            'cch_rep_position' => ['nullable', 'string', 'max:100'],

            'image' => ['nullable', 'image', 'max:10240'],
            'remove_image' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'razón social',
            'status' => 'estado',
            'monthly_fee' => 'monto a pagar',
            'joined_at' => 'fecha de ingreso',
            'person_type' => 'tipo de persona',
            'anniversary_date' => 'fecha de aniversario',
            'company' => 'nombre comercial',
            'email' => 'correo de la empresa',
            'billing_address' => 'dirección de facturación',
            'billing_district' => 'distrito',
            'billing_province' => 'provincia',
            'mailing_address' => 'dirección de correspondencia',
            'mailing_district' => 'distrito de correspondencia',
            'website' => 'página web',
            'company_size' => 'tamaño',
            'activity_type' => 'actividad',
            'sector_committee' => 'comité sectorial',
            'sub_sector' => 'sub sector',
            'profession' => 'profesión',
            'activities_started_at' => 'fecha de inicio de actividades',
            'public_registry_entry' => 'partida electrónica',
            'public_registry_title' => 'título de registros públicos',
            'main_activity' => 'actividad principal',
            'complementary_activities' => 'actividades complementarias',
            'legal_rep_name' => 'representante legal',
            'legal_rep_dni' => 'DNI del representante legal',
            'legal_rep_gender' => 'género del representante legal',
            'legal_rep_birthday' => 'cumpleaños del representante legal',
            'legal_rep_phone' => 'celular del representante legal',
            'legal_rep_email' => 'correo del representante legal',
            'legal_rep_position' => 'cargo del representante legal',
            'cch_rep_name' => 'representante ante la CCH',
            'cch_rep_dni' => 'DNI del representante ante la CCH',
            'cch_rep_gender' => 'género del representante ante la CCH',
            'cch_rep_birthday' => 'cumpleaños del representante ante la CCH',
            'cch_rep_phone' => 'celular del representante ante la CCH',
            'cch_rep_email' => 'correo del representante ante la CCH',
            'cch_rep_position' => 'cargo del representante ante la CCH',
            'image' => 'imagen',
            'notes' => 'observaciones',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'La razón social es obligatoria.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'ruc.unique' => 'Ya existe un asociado con ese RUC.',
            'email.email' => 'El correo de la empresa no es válido.',
            'email.unique' => 'Ya existe un asociado con ese correo.',
            'image.image' => 'La imagen debe ser un archivo de imagen (JPG, PNG, etc.).',
            'image.max' => 'La imagen no debe superar los 10 MB.',
        ];
    }
}
