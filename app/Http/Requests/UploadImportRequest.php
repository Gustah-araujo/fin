<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'file' => ['required', 'file', 'mimes:csv,txt,pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo é obrigatório.',
            'type.in' => 'Tipo inválido.',
            'file.required' => 'O arquivo é obrigatório.',
            'file.file' => 'O arquivo enviado é inválido.',
            'file.mimes' => 'O arquivo deve ser CSV, PDF, imagem (JPG/PNG).',
            'file.max' => 'O arquivo não pode exceder 10MB.',
        ];
    }
}
