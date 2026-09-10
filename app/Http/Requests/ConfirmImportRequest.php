<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.value' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'items.*.date' => ['required', 'date_format:Y-m-d'],
            'items.*.category_name' => ['sometimes', 'string', 'max:255'],
            'items.*.is_duplicate' => ['sometimes', 'boolean'],
            'items.*.confirm_duplicate' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Nenhum item para importar.',
            'items.min' => 'Selecione pelo menos um item.',
            'items.*.description.required' => 'A descrição é obrigatória.',
            'items.*.value.required' => 'O valor é obrigatório.',
            'items.*.value.gt' => 'O valor deve ser maior que zero.',
            'items.*.date.required' => 'A data é obrigatória.',
            'items.*.date.date_format' => 'Formato de data inválido.',
        ];
    }
}
