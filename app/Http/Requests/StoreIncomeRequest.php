<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,uuid'],
            'category_id' => ['required', 'exists:categories,uuid'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
            'installments_total' => ['sometimes', 'integer', 'between:1,60'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurring_ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->route('workspace');

            $this->validateAccountBelongsToWorkspace($validator, $workspace);

            if (! $this->validateCategory($validator, $workspace)) {
                return;
            }

            $this->validateTags($validator, $workspace);
            $this->validateRecurringAndInstallments($validator);
        });
    }

    private function validateAccountBelongsToWorkspace($validator, $workspace): void
    {
        if (! $this->filled('account_id')) {
            return;
        }

        $belongsToWorkspace = Account::where('uuid', $this->input('account_id'))
            ->where('workspace_id', $workspace->id)
            ->exists();

        if (! $belongsToWorkspace) {
            $validator->errors()->add('account_id', 'A conta selecionada não pertence a este workspace.');
        }
    }

    private function validateCategory($validator, $workspace): bool
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
        }

        if ($category->type === TransactionType::Expense) {
            $validator->errors()->add('category_id', 'Categoria de despesa não pode ser usada em receita.');
        }

        return true;
    }

    private function validateTags($validator, $workspace): void
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

    private function validateRecurringAndInstallments($validator): void
    {
        $isRecurring = (bool) $this->input('is_recurring', false);
        $installments = (int) $this->input('installments_total', 1);

        if ($isRecurring && $installments > 1) {
            $validator->errors()->add('installments_total', 'Não pode ser recorrente e parcelada ao mesmo tempo.');
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
            'account_id.required' => 'A conta é obrigatória.',
            'account_id.exists' => 'A conta selecionada é inválida.',
            'category_id.required' => 'A categoria é obrigatória.',
            'category_id.exists' => 'A categoria selecionada é inválida.',
            'tags.*.exists' => 'Tag inválida.',
            'installments_total.between' => 'O número de parcelas deve estar entre 1 e 60.',
        ];
    }
}
