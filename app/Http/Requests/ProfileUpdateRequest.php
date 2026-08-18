<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated user may edit their own profile
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            // nullable matters here beyond the other fields: a real browser
            // form always submits this field (empty string) even when the
            // user isn't touching their password, and without `nullable`
            // the `current_password` rule below would hash-check that
            // empty value against the real password and always fail.
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe otro usuario con ese correo.',
            'avatar.image' => 'La foto debe ser una imagen.',
            'avatar.max' => 'La foto no debe superar los 2 MB.',
            'current_password.required_with' => 'Ingresa tu contraseña actual para poder cambiarla.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
        ];
    }
}
