<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RentalCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rentals.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('rental_catalog_items', 'name')->ignore($this->route('catalogItem'))],
            'default_hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre del ítem.',
            'name.unique' => 'Ya existe un ítem con ese nombre.',
        ];
    }
}
