<?php

namespace App\Http\Requests;

use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rentals.manage');
    }

    public function rules(): array
    {
        return [
            'space_id' => ['required', Rule::exists(Space::class, 'id')],
            'associate_id' => ['required', 'exists:associates,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'amount' => ['required', 'numeric', 'min:0'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'space_id.required' => 'Selecciona un espacio.',
            'associate_id.required' => 'Selecciona un asociado.',
            'ends_at.after' => 'La hora de fin debe ser posterior al inicio.',
            'amount.min' => 'El monto no puede ser negativo.',
        ];
    }
}
