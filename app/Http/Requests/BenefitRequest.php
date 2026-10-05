<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('associates.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('benefits', 'name')->ignore($this->route('benefit'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'annual_quota' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre del beneficio.',
            'name.unique' => 'Ya existe un beneficio con ese nombre.',
            'annual_quota.required' => 'Indica el cupo anual.',
            'annual_quota.min' => 'El cupo anual debe ser al menos 1.',
        ];
    }
}
