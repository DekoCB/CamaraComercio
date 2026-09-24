<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProtestRegularizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('protests.manage');
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
