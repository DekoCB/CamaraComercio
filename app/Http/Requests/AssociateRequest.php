<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssociateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('associates.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            // Not a full identifier-uniqueness rule (docs/OPEN_BUSINESS_DECISIONS.md
            // #12 is still open) — just closing a real inconsistency the audit
            // found: the Excel importer already rejects a duplicate email
            // (AssociateImportService), but manual create/edit didn't, so the
            // same associate could be entered twice by hand. `email` stays
            // nullable, and Laravel's unique rule already excludes NULL rows,
            // so associates without an email never collide with each other.
            'email' => ['nullable', 'email', 'max:190', Rule::unique('associates', 'email')->ignore($this->route('associate'))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del asociado es obligatorio.',
            'email.email' => 'El correo del asociado no es válido.',
            'email.unique' => 'Ya existe un asociado con ese correo.',
        ];
    }
}
