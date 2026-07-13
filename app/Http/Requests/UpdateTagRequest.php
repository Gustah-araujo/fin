<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'color.size' => 'Cor inválida. Use o formato #RRGGBB.',
            'color.regex' => 'Cor inválida. Use o formato #RRGGBB.',
        ];
    }
}
