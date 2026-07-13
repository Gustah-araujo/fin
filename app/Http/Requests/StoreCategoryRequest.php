<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'color' => ['required', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da categoria é obrigatório.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'type.required' => 'O tipo da categoria é obrigatório.',
            'color.required' => 'A cor é obrigatória.',
            'color.size' => 'Cor inválida. Use o formato #RRGGBB.',
            'color.regex' => 'Cor inválida. Use o formato #RRGGBB.',
        ];
    }
}
