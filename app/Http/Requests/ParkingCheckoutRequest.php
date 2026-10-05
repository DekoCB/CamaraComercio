<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParkingCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('parking.manage');
    }

    public function rules(): array
    {
        return [
            'exited_at' => ['required', 'date', 'before_or_equal:now'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'exited_at.before_or_equal' => 'La hora de salida no puede ser futura.',
        ];
    }
}
