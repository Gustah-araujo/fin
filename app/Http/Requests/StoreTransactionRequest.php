<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTransactionRequest extends FormRequest
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
            'until_date' => ['nullable', 'date', 'after_or_equal:date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->route('workspace');

            if ($this->filled('account_id')) {
                $belongsToWorkspace = Account::where('uuid', $this->input('account_id'))
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
                $tagCount = Tag::whereIn('uuid', $this->input('tags'))
                    ->where('workspace_id', $workspace->id)
                    ->count();

                if ($tagCount !== count($this->input('tags'))) {
                    $validator->errors()->add('tags', 'Uma ou mais tags são inválidas.');
                }
            }

            $this->validateFrequencyDay($validator);
        });
    }

    private function validateFrequencyDay($validator): void
    {
        if (! $this->boolean('is_recurring') || ! $this->filled('frequency') || ! $this->filled('frequency_day')) {
            return;
        }

        $frequency = RecurrenceFrequency::tryFrom((string) $this->input('frequency'));
        $day = (int) $this->input('frequency_day');
        $error = match ($frequency) {
            RecurrenceFrequency::Weekly => $day < 0 || $day > 6,
            RecurrenceFrequency::Monthly => $day < 1 || $day > 31,
            default => false,
        };

        if ($error) {
            $validator->errors()->add('frequency_day', $this->frequencyDayErrorMessage($frequency));
        }
    }

    private function frequencyDayErrorMessage(?RecurrenceFrequency $frequency): string
    {
        return match ($frequency) {
            RecurrenceFrequency::Weekly => 'Dia da semana inválido.',
            RecurrenceFrequency::Monthly => 'Dia do mês inválido.',
            default => 'Dia inválido.',
        };
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
            'frequency.required_if' => 'A frequência é obrigatória para despesas recorrentes.',
            'frequency_day.required_if' => 'O dia da recorrência é obrigatório.',
            'until_date.after_or_equal' => 'A data final deve ser maior ou igual à data inicial.',
        ];
    }
}
