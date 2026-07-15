<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCardExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes', 'numeric', 'gt:0', 'max:999999999.99'],
            'date' => ['sometimes', 'date'],
            'category_id' => ['sometimes', 'exists:categories,uuid'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
            'scope' => ['sometimes', 'in:single,group'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.max' => 'A descrição não pode ter mais de 255 caracteres.',
            'value.gt' => 'O valor deve ser maior que zero.',
            'value.max' => 'O valor excede o limite permitido.',
            'date.date' => 'A data informada é inválida.',
            'scope.in' => 'O escopo deve ser "single" ou "group".',
        ];
    }
}
