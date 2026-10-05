<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParkingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('parking.manage');
    }

    public function rules(): array
    {
        return [
            'plate' => ['required', 'string', 'max:10'],
            'associate_id' => ['nullable', 'exists:associates,id'],
            'owner_name' => ['nullable', 'required_without:associate_id', 'string', 'max:150'],
            'vehicle_description' => ['nullable', 'string', 'max:150'],
            'entered_at' => ['required', 'date', 'before_or_equal:now'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate.required' => 'Ingresa la placa.',
            'owner_name.required_without' => 'Indica el nombre del dueño si el vehículo no es de un asociado.',
            'entered_at.before_or_equal' => 'La hora de entrada no puede ser futura.',
        ];
    }
}
