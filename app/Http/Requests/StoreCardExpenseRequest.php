<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Contracts\Validation\Validator;
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

            $this->validateInstallmentsTotal($validator, $installments);

            if (! $this->validateCreditCard($validator, $workspace)) {
                return;
            }

            if (! $this->validateCategory($validator, $workspace)) {
                return;
            }

            $this->validateAccountConflict($validator);
            $this->validateTags($validator, $workspace);
        });
    }

    private function validateInstallmentsTotal(Validator $validator, int $installments): void
    {
        if ($installments > 1 && ! $this->filled('total_value')) {
            $validator->errors()->add('total_value', 'O valor total é obrigatório para compras parceladas.');
        }
    }

    private function validateCreditCard(Validator $validator, Workspace $workspace): bool
    {
        if (! $this->filled('credit_card_id')) {
            return true;
        }

        $card = CreditCard::where('uuid', $this->input('credit_card_id'))
            ->where('workspace_id', $workspace->id)
            ->first();

        if (! $card) {
            $validator->errors()->add('credit_card_id', 'O cartão selecionado não pertence a este workspace.');

            return false;
        }

        if ($card->trashed()) {
            $validator->errors()->add('credit_card_id', 'Não é possível registrar despesas em um cartão arquivado.');
        }

        return true;
    }

    private function validateCategory(Validator $validator, Workspace $workspace): bool
    {
        if (! $this->filled('category_id')) {
            return true;
        }

        $category = Category::where('uuid', $this->input('category_id'))->first();

        if (! $category) {
            $validator->errors()->add('category_id', 'A categoria selecionada é inválida.');

            return false;
        }

        if ($category->workspace_id !== $workspace->id) {
            $validator->errors()->add('category_id', 'A categoria selecionada não pertence a este workspace.');

            return false;
        }

        if ($category->type === TransactionType::Income) {
            $validator->errors()->add('category_id', 'Esta categoria não aceita despesas.');
        }

        return true;
    }

    private function validateAccountConflict(Validator $validator): void
    {
        if ($this->filled('account_id')) {
            $validator->errors()->add('account_id', 'Uma transação deve ter conta OU cartão, nunca ambos.');
        }
    }

    private function validateTags(Validator $validator, Workspace $workspace): void
    {
        if (! $this->filled('tags')) {
            return;
        }

        $tagCount = Tag::whereIn('uuid', $this->input('tags'))
            ->where('workspace_id', $workspace->id)
            ->count();

        if ($tagCount !== count($this->input('tags'))) {
            $validator->errors()->add('tags', 'Uma ou mais tags são inválidas.');
        }
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
