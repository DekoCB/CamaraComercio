<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('billing.edit');
    }

    public function rules(): array
    {
        return [
            'receipt_number' => ['nullable', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'El monto debe ser mayor a cero.',
            'due_date.after_or_equal' => 'La fecha límite no puede ser anterior a la fecha de emisión.',
        ];
    }
}
