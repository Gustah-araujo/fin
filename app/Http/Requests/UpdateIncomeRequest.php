<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIncomeRequest extends FormRequest
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
            'account_id' => ['sometimes', 'required', 'exists:accounts,uuid'],
            'category_id' => ['sometimes', 'required', 'exists:categories,uuid'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
            'scope' => ['sometimes', 'in:single,future'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->route('workspace');

            $this->validateAccount($validator, $workspace);
            $this->validateCategory($validator, $workspace);
            $this->validateTags($validator, $workspace);

            if ($this->filled('credit_card_id')) {
                $validator->errors()->add('credit_card_id', 'Uma receita não pode estar vinculada a um cartão de crédito.');
            }

            if ($this->input('scope') === 'future' && ($this->filled('frequency') || $this->filled('start_date'))) {
                $validator->errors()->add('scope', 'Para alterar a frequência, edite a recorrência na página de gestão.');
            }
        });
    }

    private function validateAccount($validator, $workspace): void
    {
        if (! $this->filled('account_id')) {
            return;
        }

        $account = Account::withTrashed()->where('uuid', $this->input('account_id'))->first();

        if (! $account) {
            $validator->errors()->add('account_id', 'A conta selecionada é inválida.');

            return;
        }

        if ($account->workspace_id !== $workspace->id) {
            $validator->errors()->add('account_id', 'A conta selecionada não pertence a este workspace.');

            return;
        }

        if ($account->trashed()) {
            $validator->errors()->add('account_id', 'A conta selecionada foi arquivada.');
        }
    }

    private function validateCategory($validator, $workspace): void
    {
        if (! $this->filled('category_id')) {
            return;
        }

        $category = Category::where('uuid', $this->input('category_id'))->first();

        if (! $category) {
            $validator->errors()->add('category_id', 'A categoria selecionada é inválida.');

            return;
        }

        if ($category->workspace_id !== $workspace->id) {
            $validator->errors()->add('category_id', 'A categoria selecionada não pertence a este workspace.');
        }

        if ($category->type === TransactionType::Expense) {
            $validator->errors()->add('category_id', 'Esta categoria não aceita receitas.');
        }
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
        ];
    }
}
