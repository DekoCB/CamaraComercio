<?php

namespace App\Http\Requests;

use App\Models\Protest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProtestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('protests.manage');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(Protest::TYPES))],
            'channel' => ['required', Rule::in(array_keys(Protest::CHANNELS))],
            'instrument_type' => ['nullable', Rule::in(array_keys(Protest::INSTRUMENT_TYPES))],
            'debtor_name' => ['required', 'string', 'max:255'],
            'debtor_document' => ['nullable', 'string', 'max:20'],
            'creditor_name' => ['required', 'string', 'max:255'],
            'creditor_document' => ['nullable', 'string', 'max:20'],
            'associate_id' => ['nullable', 'exists:associates,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'registered_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Selecciona si es un protesto o una mora.',
            'channel.required' => 'Selecciona la vía.',
            'debtor_name.required' => 'Ingresa el nombre del deudor.',
            'creditor_name.required' => 'Ingresa el nombre del acreedor.',
            'registered_at.before_or_equal' => 'La fecha de registro no puede ser futura.',
        ];
    }
}
