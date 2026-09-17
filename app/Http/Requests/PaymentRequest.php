<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.register');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'operation_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'El monto del pago debe ser mayor a cero.',
            'paid_at.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'method.required' => 'Indica el método de pago.',
            'method.in' => 'El método de pago no es válido.',
        ];
    }
}
