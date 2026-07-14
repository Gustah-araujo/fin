<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'closing_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['required', 'integer', 'between:1,31'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do cartão é obrigatório.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'credit_limit.required' => 'O limite do cartão é obrigatório.',
            'credit_limit.numeric' => 'O limite deve ser um valor numérico.',
            'credit_limit.min' => 'O limite não pode ser negativo.',
            'closing_day.required' => 'O dia de fechamento é obrigatório.',
            'closing_day.integer' => 'O dia de fechamento deve ser um número inteiro.',
            'closing_day.between' => 'O dia de fechamento deve estar entre 1 e 31.',
            'due_day.required' => 'O dia de vencimento é obrigatório.',
            'due_day.integer' => 'O dia de vencimento deve ser um número inteiro.',
            'due_day.between' => 'O dia de vencimento deve estar entre 1 e 31.',
        ];
    }
}
