<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('billing.generate');
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'confirm' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'period.date_format' => 'El período debe tener el formato AAAA-MM (por ejemplo, 2026-08).',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'due_date.after_or_equal' => 'La fecha límite no puede ser anterior a la fecha de emisión.',
            'confirm.accepted' => 'Debe confirmar la generación masiva de facturas.',
        ];
    }
}
