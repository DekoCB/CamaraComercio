<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RoleAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.roles');
    }

    public function rules(): array
    {
        return [
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
            'modules' => ['array'],
            'modules.*' => ['integer', 'exists:modules,id'],
        ];
    }
}
