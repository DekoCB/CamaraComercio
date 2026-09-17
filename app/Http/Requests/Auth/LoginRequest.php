<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.required' => 'Selecciona el rol con el que vas a ingresar.',
        ];
    }
}
