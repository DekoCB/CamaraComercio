<?php

namespace App\Http\Requests;

use App\Models\Associate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssociateDeclarationRequest extends FormRequest
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
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_district' => ['nullable', 'string', 'max:100'],
            'billing_province' => ['nullable', 'string', 'max:100'],
            'billing_department' => ['nullable', 'string', 'max:100'],
            'legal_rep_name' => ['required', 'string', 'max:150'],
            'legal_rep_dni' => ['nullable', 'string', 'max:20'],

            'membership_status' => ['required', Rule::in(Associate::MEMBERSHIP_DECLARATION_OPTIONS)],
            'declaration_date' => ['required', 'date', 'before_or_equal:today'],

            // Opcionales — si el asociado ya tiene firma/huella escaneadas
            // con nitidez, se insertan en el PDF; si no, quedan en blanco
            // para firmar a mano tras imprimir.
            'signature' => ['nullable', 'image', 'max:10240'],
            'fingerprint' => ['nullable', 'image', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'La razón social es obligatoria.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'legal_rep_name.required' => 'Indica quién declara (nombre completo).',
            'membership_status.required' => 'Indica si declara como aspirante o como asociado.',
            'declaration_date.before_or_equal' => 'La fecha no puede ser futura.',
            'signature.image' => 'La firma debe ser una imagen (JPG, PNG, etc.).',
            'fingerprint.image' => 'La huella digital debe ser una imagen (JPG, PNG, etc.).',
        ];
    }
}
