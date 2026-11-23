<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateRecurrenceRequest extends FormRequest
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
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'value' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:999999999.99'],
            'account_id' => ['sometimes', 'nullable', 'exists:accounts,uuid'],
            'credit_card_id' => ['sometimes', 'nullable', 'exists:credit_cards,uuid'],
            'category_id' => ['sometimes', 'required', 'exists:categories,uuid'],
            'frequency' => ['sometimes', new Enum(RecurrenceFrequency::class)],
            'frequency_day' => ['sometimes', 'integer'],
            'start_date' => ['sometimes', 'date'],
            'until_date' => ['nullable', 'date'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
            'buffer_ahead' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'propagate_to_future' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $workspace = $this->route('workspace');

            if (! $workspace instanceof Workspace) {
                return;
            }

            $this->validateAccount($validator, $workspace);
            $this->validateCategory($validator, $workspace);
            $this->validateTags($validator, $workspace);
            $this->validateFrequencyDay($validator);
        });
    }

    private function validateAccount(Validator $validator, Workspace $workspace): void
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

    private function validateCategory(Validator $validator, Workspace $workspace): void
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

        $recurrence = $this->route('recurrence');

        if (! $recurrence instanceof Recurrence) {
            return;
        }

        if ($this->typeIs($recurrence->type, TransactionType::Expense) && $this->typeIs($category->type, TransactionType::Income)) {
            $validator->errors()->add('category_id', 'Esta categoria não aceita despesas.');
        }

        if ($this->typeIs($recurrence->type, TransactionType::Income) && $this->typeIs($category->type, TransactionType::Expense)) {
            $validator->errors()->add('category_id', 'Esta categoria não aceita receitas.');
        }
    }

    private function typeIs(mixed $type, TransactionType $expected): bool
    {
        return $type instanceof TransactionType
            ? $type === $expected
            : $type === $expected->value;
    }

    private function validateTags(Validator $validator, Workspace $workspace): void
    {
        if (! $this->filled('tags')) {
            return;
        }

        $tags = $this->input('tags');

        $tagCount = Tag::whereIn('uuid', $tags)
            ->where('workspace_id', $workspace->id)
            ->count();

        if ($tagCount !== (is_array($tags) ? count($tags) : 0)) {
            $validator->errors()->add('tags', 'Uma ou mais tags são inválidas.');
        }
    }

    private function validateFrequencyDay(Validator $validator): void
    {
        if (! $this->filled('frequency_day')) {
            return;
        }

        $frequency = $this->resolveFrequency();

        if (! $frequency instanceof RecurrenceFrequency) {
            return;
        }

        $day = $this->dayValue();

        $this->validateDayRange($validator, $frequency, $day);
    }

    private function resolveFrequency(): ?RecurrenceFrequency
    {
        if ($this->filled('frequency')) {
            $frequencyInput = $this->input('frequency');

            return is_string($frequencyInput) ? RecurrenceFrequency::tryFrom($frequencyInput) : null;
        }

        $recurrence = $this->route('recurrence');

        if (! $recurrence instanceof Recurrence) {
            return null;
        }

        return $this->frequencyFromModel($recurrence->frequency);
    }

    private function frequencyFromModel(mixed $frequency): ?RecurrenceFrequency
    {
        if ($frequency instanceof RecurrenceFrequency) {
            return $frequency;
        }

        return is_string($frequency) ? RecurrenceFrequency::tryFrom($frequency) : null;
    }

    private function dayValue(): int
    {
        $day = $this->input('frequency_day');

        return is_numeric($day) ? (int) $day : 0;
    }

    private function validateDayRange(Validator $validator, RecurrenceFrequency $frequency, int $day): void
    {
        if ($frequency === RecurrenceFrequency::Weekly && ($day < 0 || $day > 6)) {
            $validator->errors()->add('frequency_day', 'Dia da semana inválido.');
        }

        if ($frequency === RecurrenceFrequency::Monthly && ($day < 1 || $day > 31)) {
            $validator->errors()->add('frequency_day', 'Dia do mês inválido.');
        }
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
            'account_id.exists' => 'A conta selecionada é inválida.',
            'category_id.required' => 'A categoria é obrigatória.',
            'category_id.exists' => 'A categoria selecionada é inválida.',
            'tags.*.exists' => 'Tag inválida.',
            'until_date.date' => 'A data final informada é inválida.',
        ];
    }
}
