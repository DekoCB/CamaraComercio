<?php

namespace App\Http\Requests;

use App\Models\AssociateDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssociateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('associates.manage');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(AssociateDocument::TYPES))],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Indica el tipo de documento.',
            'file.required' => 'Selecciona un archivo.',
            'file.mimes' => 'El archivo debe ser un PDF, JPG o PNG.',
            'file.max' => 'El archivo no debe superar los 10 MB.',
        ];
    }
}
