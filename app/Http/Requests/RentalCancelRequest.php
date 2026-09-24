<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RentalCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rentals.manage');
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
            'reason.required' => 'Indica el motivo de la cancelación.',
        ];
    }
}
