<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.modules');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_\-]+$/', 'unique:modules,code'],
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:60'],
            'route' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'El código del módulo solo puede contener letras minúsculas, números, guiones y guiones bajos.',
            'code.unique' => 'Ya existe un módulo con ese código.',
        ];
    }
}
