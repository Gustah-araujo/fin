<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use App\Models\Workspace;
use App\Rules\AfterOrEqualDateRule;
use App\Services\TransactionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'date' => ['required', 'date'],
            'account_id' => ['nullable', 'exists:accounts,uuid'],
            'credit_card_id' => ['nullable', 'exists:credit_cards,uuid'],
            'category_id' => ['required', 'exists:categories,uuid'],
            'expense_group_id' => ['nullable', Rule::exists('expense_groups', 'uuid')->where(
                'workspace_id',
                $this->workspaceId(),
            )],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
            'is_recurring' => ['sometimes', 'boolean'],
            'frequency' => ['required_if:is_recurring,true', new Enum(RecurrenceFrequency::class)],
            'frequency_day' => ['required_if:is_recurring,true', 'integer'],
            'until_date' => ['nullable', 'date', new AfterOrEqualDateRule('date')],
            'buffer_ahead' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:48'],
            'total_value' => ['nullable', 'numeric', 'min:0.01'],
            'installment_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'is_paid' => ['sometimes', 'boolean'],
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
            TransactionValidator::validateStore($validator, $this);
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
            'frequency.required_if' => 'A frequência é obrigatória para despesas recorrentes.',
            'frequency_day.required_if' => 'O dia da recorrência é obrigatório.',
            'total_value.required_if' => 'O valor total é obrigatório para compras parceladas.',
            'total_value.numeric' => 'O valor total deve ser um número.',
            'total_value.min' => 'O valor total deve ser maior que zero.',
            'installments.max' => 'O número máximo de parcelas é 48.',
            'installments.min' => 'O número mínimo de parcelas é 1.',
            'installment_day.min' => 'O dia do mês deve ser maior que zero.',
            'installment_day.max' => 'O dia do mês não pode ser maior que 31.',
        ];
    }
}
