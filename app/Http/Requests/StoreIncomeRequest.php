<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Rules\AfterOrEqualDateRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

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
            'is_recurring' => ['sometimes', 'boolean'],
            'frequency' => ['required_if:is_recurring,true', new Enum(RecurrenceFrequency::class)],
            'frequency_day' => ['required_if:is_recurring,true', 'integer'],
            'until_date' => ['nullable', 'date', new AfterOrEqualDateRule('date')],
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

            $this->validateFrequencyDay($validator);
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

    private function validateFrequencyDay($validator): void
    {
        if (! $this->boolean('is_recurring')) {
            return;
        }

        if (! $this->filled('frequency') || ! $this->filled('frequency_day')) {
            return;
        }

        $frequency = RecurrenceFrequency::tryFrom((string) $this->input('frequency'));
        $day = (int) $this->input('frequency_day');

        $this->validateDayRange($validator, $frequency, $day);
    }

    private function validateDayRange($validator, ?RecurrenceFrequency $frequency, int $day): void
    {
        if ($frequency === RecurrenceFrequency::Weekly && $this->isInvalidWeekday($day)) {
            $validator->errors()->add('frequency_day', 'Dia da semana inválido.');
        }

        if ($frequency === RecurrenceFrequency::Monthly && $this->isInvalidMonthDay($day)) {
            $validator->errors()->add('frequency_day', 'Dia do mês inválido.');
        }
    }

    private function isInvalidWeekday(int $day): bool
    {
        return $day < 0 || $day > 6;
    }

    private function isInvalidMonthDay(int $day): bool
    {
        return $day < 1 || $day > 31;
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
            'frequency.required_if' => 'A frequência é obrigatória para receitas recorrentes.',
            'frequency_day.required_if' => 'O dia da recorrência é obrigatório.',

        ];
    }
}
