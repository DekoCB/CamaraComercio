<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentVoidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.void');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Debe indicar el motivo de la anulación.',
        ];
    }
}
