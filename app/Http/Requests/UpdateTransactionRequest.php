<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Category;
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
            'account_id' => ['sometimes', 'required', 'exists:accounts,uuid'],
            'category_id' => ['sometimes', 'required', 'exists:categories,uuid'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->route('workspace');

            if ($this->filled('account_id')) {
                $belongsToWorkspace = \App\Models\Account::where('uuid', $this->input('account_id'))
                    ->where('workspace_id', $workspace->id)
                    ->exists();

                if (! $belongsToWorkspace) {
                    $validator->errors()->add('account_id', 'A conta selecionada não pertence a este workspace.');
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
                }

                if ($category->type === TransactionType::Income) {
                    $validator->errors()->add('category_id', 'Esta categoria não aceita despesas.');
                }
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
            'account_id.required' => 'A conta é obrigatória.',
            'account_id.exists' => 'A conta selecionada é inválida.',
            'category_id.required' => 'A categoria é obrigatória.',
            'category_id.exists' => 'A categoria selecionada é inválida.',
            'tags.*.exists' => 'Tag inválida.',
        ];
    }
}
