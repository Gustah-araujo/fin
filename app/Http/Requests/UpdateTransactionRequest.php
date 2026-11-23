<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Workspace;
use App\Services\TransactionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|object>>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'nullable', 'exists:accounts,uuid'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'value' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:999999999.99'],
            'date' => ['sometimes', 'required', 'date'],
            'category_id' => ['sometimes', 'required', 'exists:categories,uuid'],
            'expense_group_id' => ['sometimes', 'nullable', Rule::exists('expense_groups', 'uuid')->where(
                'workspace_id',
                $this->workspaceId(),
            )],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'scope' => ['sometimes', 'in:single,group'],
        ];
    }

    private function workspaceId(): string
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace ? (string) $workspace->id : '';
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            TransactionValidator::validateUpdate($validator, $this);
        });
    }

    /**
     * @return array<string, string>
     */
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
            'expense_group_id.exists' => 'O grupo de despesa selecionado é inválido.',
            'tags.*.exists' => 'Tag inválida.',
            'scope.in' => 'O escopo deve ser "single" ou "group".',
        ];
    }
}
