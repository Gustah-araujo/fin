<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\CreditCard;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'total_value' => ['sometimes', 'numeric', 'gt:0', 'max:999999999.99'],
            'date' => ['required', 'date'],
            'credit_card_id' => ['required', 'exists:credit_cards,uuid'],
            'category_id' => ['required', 'exists:categories,uuid'],
            'installments' => ['sometimes', 'integer', 'between:1,48'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->route('workspace');
            $installments = (int) ($this->input('installments', 1));

            if ($installments > 1 && ! $this->filled('total_value')) {
                $validator->errors()->add('total_value', 'O valor total é obrigatório para compras parceladas.');
            }

            if ($this->filled('credit_card_id')) {
                $card = CreditCard::where('uuid', $this->input('credit_card_id'))
                    ->where('workspace_id', $workspace->id)
                    ->first();

                if (! $card) {
                    $validator->errors()->add('credit_card_id', 'O cartão selecionado não pertence a este workspace.');
                    return;
                }

                if ($card->trashed()) {
                    $validator->errors()->add('credit_card_id', 'Não é possível registrar despesas em um cartão arquivado.');
                }
            }

            if ($this->filled('category_id')) {
                $category = Category::where('uuid', $this->input('category_id'))->first();

                if (! $category) {
                    $validator->errors()->add('category_id', 'A categoria selecionada é inválida.');
                    return;
                }

                if ($category->workspace_id !== $workspace->id) {
                    $validator->errors()->add('category_id', 'A categoria selecionada não pertence a este workspace.');
                    return;
                }

                if ($category->type === TransactionType::Income) {
                    $validator->errors()->add('category_id', 'Esta categoria não aceita despesas.');
                }
            }

            if ($this->filled('account_id')) {
                $validator->errors()->add('account_id', 'Uma transação deve ter conta OU cartão, nunca ambos.');
            }

            if ($this->filled('tags')) {
                $tagCount = \App\Models\Tag::whereIn('uuid', $this->input('tags'))
                    ->where('workspace_id', $workspace->id)
                    ->count();

                if ($tagCount !== count($this->input('tags'))) {
                    $validator->errors()->add('tags', 'Uma ou mais tags são inválidas.');
                }
            }
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
            'credit_card_id.required' => 'O cartão é obrigatório.',
            'credit_card_id.exists' => 'O cartão selecionado é inválido.',
            'category_id.required' => 'A categoria é obrigatória.',
            'category_id.exists' => 'A categoria selecionada é inválida.',
            'installments.between' => 'O número de parcelas deve estar entre 1 e 48.',
            'total_value.gt' => 'O valor total deve ser maior que zero.',
            'total_value.max' => 'O valor total excede o limite permitido.',
            'tags.*.exists' => 'Tag inválida.',
        ];
    }
}
