<?php

namespace App\Http\Requests;

use App\Models\Benefit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BenefitUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('associates.manage');
    }

    public function rules(): array
    {
        return [
            'benefit_id' => ['required', Rule::exists(Benefit::class, 'id')],
            'used_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'benefit_id.required' => 'Selecciona un beneficio.',
            'used_at.before_or_equal' => 'La fecha de uso no puede ser futura.',
        ];
    }
}
