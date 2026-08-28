<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registering a payment from the Pagos tab (no invoice already selected
 * via the URL, unlike PaymentRequest which is scoped to
 * invoices/{invoice}/payments) — same rules, plus which invoice it's for.
 */
class PaymentQuickRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.register');
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Debe seleccionar una factura.',
            'invoice_id.exists' => 'La factura seleccionada no existe.',
            'amount.min' => 'El monto del pago debe ser mayor a cero.',
            'paid_at.before_or_equal' => 'La fecha de pago no puede ser futura.',
        ];
    }
}
