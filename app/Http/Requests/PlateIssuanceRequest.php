<?php

namespace App\Http\Requests;

use App\Models\PlateIssuance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlateIssuanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('plates.manage');
    }

    public function rules(): array
    {
        return [
            'procedure_type' => ['required', Rule::in(array_keys(PlateIssuance::PROCEDURE_TYPES))],
            'other_description' => ['nullable', 'required_if:procedure_type,'.PlateIssuance::PROCEDURE_OTROS, 'string', 'max:150'],
            'plate_number' => ['nullable', 'string', 'max:10'],
            'associate_id' => ['nullable', 'exists:associates,id'],
            'client_name' => ['nullable', 'required_without:associate_id', 'string', 'max:150'],
            'vehicle_description' => ['nullable', 'string', 'max:150'],
            'receipt_type' => ['required', Rule::in(array_keys(PlateIssuance::RECEIPT_TYPES))],
            'receipt_number' => ['nullable', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0'],
            'issued_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'procedure_type.required' => 'Selecciona el tipo de trámite.',
            'other_description.required_if' => 'Describe el trámite cuando el tipo es "Otros".',
            'associate_id.required_without' => 'Selecciona un asociado o escribe el nombre del solicitante.',
            'client_name.required_without' => 'Selecciona un asociado o escribe el nombre del solicitante.',
            'receipt_type.required' => 'Selecciona el tipo de comprobante.',
            'issued_at.before_or_equal' => 'La fecha del trámite no puede ser futura.',
        ];
    }
}
