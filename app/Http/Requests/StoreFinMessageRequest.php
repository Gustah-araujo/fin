<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'A mensagem não pode estar vazia.',
            'content.max' => 'A mensagem é muito longa.',
        ];
    }
}
