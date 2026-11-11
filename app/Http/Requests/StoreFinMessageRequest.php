<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFinMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:4000'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,csv,xlsx,xls,txt,png,jpg,jpeg,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => 'A mensagem é muito longa.',
            'attachments.array' => 'Os anexos são inválidos.',
            'attachments.max' => 'Você pode anexar no máximo 5 arquivos.',
            'attachments.*.file' => 'O arquivo enviado é inválido.',
            'attachments.*.mimes' => 'Formatos permitidos: PDF, CSV, XLSX, XLS, TXT, PNG, JPG, JPEG ou WEBP.',
            'attachments.*.max' => 'Cada arquivo não pode exceder 10MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();

            $content = trim((string) ($data['content'] ?? ''));
            $attachments = is_array($data['attachments'] ?? null) ? array_filter($data['attachments']) : [];

            if ($content === '' && $attachments === []) {
                $validator->errors()->add('content', 'Envie uma mensagem ou anexe pelo menos um arquivo.');
            }
        });
    }
}
