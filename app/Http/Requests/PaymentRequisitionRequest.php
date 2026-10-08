<?php

namespace App\Http\Requests;

use App\Models\PaymentRequisition;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rentals.requisitions.manage');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(PaymentRequisition::TYPES))],
            'requester_area' => ['nullable', 'string', 'max:150'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_role' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'issued_at' => ['required', 'date', 'before_or_equal:today'],
            'beneficiary_name' => ['required', 'string', 'max:150'],
            'bank_details' => ['nullable', 'string', 'max:1000'],
            'provider_ruc' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // El amount de cada fila queda nullable a propósito: el
            // formulario siempre manda algunas filas en blanco de sobra
            // (igual que Alquileres con sus líneas de ítems), y
            // PaymentRequisitionService las descarta en silencio. Que
            // haya al menos un ítem real con importe se exige aparte, en
            // withValidator() — una regla declarativa no alcanza para
            // "al menos un elemento del array cumple X".
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_date' => ['nullable', 'date'],
            'items.*.reference' => ['nullable', 'string', 'max:40'],
            'items.*.description' => ['required_with:items.*.amount', 'nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = collect($this->input('items', []));
            $hasRealItem = $items->contains(fn (array $item) => (float) ($item['amount'] ?? 0) > 0);

            if (! $hasRealItem) {
                $validator->errors()->add('items', 'Agrega al menos un ítem con un importe mayor a cero.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Selecciona el tipo de requerimiento.',
            'recipient_name.required' => 'Ingresa a quién va dirigido.',
            'subject.required' => 'Describe el asunto del requerimiento.',
            'issued_at.before_or_equal' => 'La fecha no puede ser futura.',
            'beneficiary_name.required' => 'Ingresa el titular que recibe el pago.',
            'items.required' => 'Agrega al menos un ítem.',
            'items.min' => 'Agrega al menos un ítem.',
            'items.*.description.required_with' => 'Describe cada ítem con monto.',
        ];
    }
}
