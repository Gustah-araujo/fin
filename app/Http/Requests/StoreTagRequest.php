<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da tag é obrigatório.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'color.required' => 'A cor é obrigatória.',
            'color.size' => 'Cor inválida. Use o formato #RRGGBB.',
            'color.regex' => 'Cor inválida. Use o formato #RRGGBB.',
        ];
    }
}
