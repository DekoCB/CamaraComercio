<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rentals.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('spaces', 'name')->ignore($this->route('space'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre del espacio.',
            'name.unique' => 'Ya existe un espacio con ese nombre.',
        ];
    }
}
