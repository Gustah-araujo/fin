<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\TransactionValidator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'value' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:999999999.99'],
            'date' => ['sometimes', 'required', 'date'],
            'category_id' => ['sometimes', 'required', 'exists:categories,uuid'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'scope' => ['sometimes', 'in:single,group'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            TransactionValidator::validateUpdate($validator, $this);
        });
    }

    public function messages(): array
    {
        return [
            'description.required' => 'A descrição é obrigatória.',
            'description.max' => 'A descrição não pode ter mais de 255 caracteres.',
            'value.required' => 'O valor é obrigatório.',
            'value.numeric' => 'O valor deve ser um número.',
            'value.gt' => 'O valor deve ser maior que zero.',
            'value.max' => 'O valor excede o limite permitido.',
            'date.required' => 'A data é obrigatória.',
            'date.date' => 'A data informada é inválida.',
            'category_id.required' => 'A categoria é obrigatória.',
            'category_id.exists' => 'A categoria selecionada é inválida.',
            'tags.*.exists' => 'Tag inválida.',
            'scope.in' => 'O escopo deve ser "single" ou "group".',
        ];
    }
}
