<?php

namespace App\Http\Requests;

use App\Models\RentalCatalogItem;
use App\Models\RentalCatering;
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
            // La cotización real puede ir dirigida a alguien que no es
            // asociado ("Sres. BRANKO PERÚ") — uno de los dos es obligatorio.
            'associate_id' => ['nullable', 'required_without:client_name', 'exists:associates,id'],
            'client_name' => ['nullable', 'required_without:associate_id', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'amount' => ['required', 'numeric', 'min:0'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'line_items' => ['nullable', 'array'],
            'line_items.*.catalog_item_id' => ['nullable', Rule::exists(RentalCatalogItem::class, 'id')],
            'line_items.*.description' => ['nullable', 'string', 'max:150'],
            'line_items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'line_items.*.hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],

            'catering.people_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'catering.drink_option' => ['nullable', Rule::in(RentalCatering::DRINK_OPTIONS)],
            'catering.sandwich_option' => ['nullable', Rule::in(RentalCatering::SANDWICH_OPTIONS)],
            'catering.dessert_option' => ['nullable', Rule::in(RentalCatering::DESSERT_OPTIONS)],
            'catering.daily_cost' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'catering.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'space_id.required' => 'Selecciona un espacio.',
            'associate_id.required_without' => 'Selecciona un asociado o escribe el nombre del cliente.',
            'client_name.required_without' => 'Selecciona un asociado o escribe el nombre del cliente.',
            'ends_at.after' => 'La hora de fin debe ser posterior al inicio.',
            'amount.min' => 'El monto no puede ser negativo.',
        ];
    }
}
