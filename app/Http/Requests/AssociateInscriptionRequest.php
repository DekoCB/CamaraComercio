<?php

namespace App\Http\Requests;

use App\Models\Associate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssociateInscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('associates.manage');
    }

    protected function prepareForValidation(): void
    {
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
            'name' => ['required', 'string', 'max:150'],
            'ruc' => ['nullable', 'digits:11', Rule::unique('associates', 'ruc')->ignore($ignore)],
            'company' => ['nullable', 'string', 'max:150'],
            'activities_started_at' => ['nullable', 'date'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_district' => ['nullable', 'string', 'max:100'],
            'billing_province' => ['nullable', 'string', 'max:100'],
            'mailing_address' => ['nullable', 'string', 'max:255'],
            'mailing_district' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('associates', 'email')->ignore($ignore)],
            'website' => ['nullable', 'string', 'max:190'],
            'public_registry_entry' => ['nullable', 'string', 'max:100'],
            'public_registry_title' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'legal_rep_name' => ['nullable', 'string', 'max:150'],
            'legal_rep_dni' => ['nullable', 'string', 'max:20'],
            'legal_rep_position' => ['nullable', 'string', 'max:100'],
            'legal_rep_phone' => ['nullable', 'string', 'max:40'],
            'legal_rep_email' => ['nullable', 'email', 'max:190'],
            'legal_rep_birthday' => ['nullable', 'date'],

            'cch_rep_name' => ['nullable', 'string', 'max:150'],
            'cch_rep_dni' => ['nullable', 'string', 'max:20'],
            'cch_rep_position' => ['nullable', 'string', 'max:100'],
            'cch_rep_phone' => ['nullable', 'string', 'max:40'],
            'cch_rep_email' => ['nullable', 'email', 'max:190'],
            'cch_rep_birthday' => ['nullable', 'date'],

            'main_activity' => ['nullable', Rule::in(Associate::ACTIVITY_OPTIONS)],
            'complementary_activities' => ['nullable', 'array'],
            'complementary_activities.*' => [Rule::in(Associate::ACTIVITY_OPTIONS)],
            'ciiu' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:100'],

            'executives' => ['nullable', 'array'],
            'executives.*.name' => ['nullable', 'string', 'max:150'],
            'executives.*.position' => ['nullable', 'string', 'max:100'],
            'executives.*.phone' => ['nullable', 'string', 'max:40'],
            'executives.*.birthday' => ['nullable', 'date'],

            'products' => ['nullable', 'array'],
            'products.*.description' => ['nullable', 'string', 'max:255'],
            'products.*.is_fabrica' => ['nullable', 'boolean'],
            'products.*.is_produce' => ['nullable', 'boolean'],
            'products.*.is_comercializa' => ['nullable', 'boolean'],
            'products.*.is_importa' => ['nullable', 'boolean'],
            'products.*.is_servicios' => ['nullable', 'boolean'],
            'products.*.is_exporta' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'La razón social es obligatoria.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
        ];
    }
}
