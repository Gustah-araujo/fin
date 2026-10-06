<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TransactionValidator
{
    public static function validateStore(Validator $validator, FormRequest $request): void
    {
        $workspace = $request->route('workspace');

        self::validateSourceMutualExclusion($validator, $request);
        self::validateCreditCard($validator, $request, $workspace);
        self::validateInstallmentsTotalValue($validator, $request);
        self::validateRecurrenceCardInstallments($validator, $request);
        self::validateAccountBelongsToWorkspace($validator, $request, $workspace);
        self::validateCategory($validator, $request, $workspace);
        self::validateTags($validator, $request, $workspace);
        self::validateFrequencyDay($validator, $request);
    }

    public static function validateUpdate(Validator $validator, FormRequest $request): void
    {
        $workspace = $request->route('workspace');

        self::validateAccountBelongsToWorkspace($validator, $request, $workspace);
        self::validateCategory($validator, $request, $workspace);
        self::validateTags($validator, $request, $workspace);
    }

    private static function validateSourceMutualExclusion(Validator $validator, FormRequest $request): void
    {
        $accountId = $request->input('account_id');
        $creditCardId = $request->input('credit_card_id');

        if ($accountId && $creditCardId) {
            $validator->errors()->add('account_id', 'Uma transação deve ter conta OU cartão, nunca ambos.');
        } elseif (! $accountId && ! $creditCardId) {
            $validator->errors()->add('account_id', 'Uma transação deve ter uma conta ou cartão selecionado.');
        }
    }

    private static function validateCreditCard(Validator $validator, FormRequest $request, mixed $workspace): void
    {
        $creditCardId = $request->input('credit_card_id');

        if (! $creditCardId) {
            return;
        }

        $card = CreditCard::where('uuid', $creditCardId)->whereNull('deleted_at')->first();

        if (! $card || $card->workspace_id !== $workspace->id) {
            $validator->errors()->add('credit_card_id', 'Cartão não encontrado ou não pertence ao workspace.');
        }
    }

    private static function validateInstallmentsTotalValue(Validator $validator, FormRequest $request): void
    {
        $installments = (int) $request->input('installments', 1);

        if ($installments > 1 && ! $request->input('total_value')) {
            $validator->errors()->add('total_value', 'O valor total é obrigatório para compras parceladas.');
        }
    }

    private static function validateRecurrenceCardInstallments(Validator $validator, FormRequest $request): void
    {
        $installments = (int) $request->input('installments', 1);
        $isRecurring = $request->boolean('is_recurring');
        $creditCardId = $request->input('credit_card_id');

        if ($isRecurring && $creditCardId && $installments > 1) {
            $validator->errors()->add('installments', 'Despesas recorrentes em cartão não podem ser parceladas.');
        }
    }

    private static function validateAccountBelongsToWorkspace(Validator $validator, FormRequest $request, mixed $workspace): void
    {
        if (! $request->filled('account_id')) {
            return;
        }

        $belongsToWorkspace = Account::where('uuid', $request->input('account_id'))
            ->where('workspace_id', $workspace->id)
            ->exists();

        if (! $belongsToWorkspace) {
            $validator->errors()->add('account_id', 'A conta selecionada não pertence a este workspace.');
        }
    }

    private static function validateCategory(Validator $validator, FormRequest $request, mixed $workspace): void
    {
        if (! $request->filled('category_id')) {
            return;
        }

        $category = Category::where('uuid', $request->input('category_id'))->first();

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

    private static function validateTags(Validator $validator, FormRequest $request, mixed $workspace): void
    {
        if (! $request->filled('tags')) {
            return;
        }

        $tagCount = Tag::whereIn('uuid', $request->input('tags'))
            ->where('workspace_id', $workspace->id)
            ->count();

        if ($tagCount !== count($request->input('tags'))) {
            $validator->errors()->add('tags', 'Uma ou mais tags são inválidas.');
        }
    }

    private static function validateFrequencyDay(Validator $validator, FormRequest $request): void
    {
        if (! $request->boolean('is_recurring') || ! $request->filled('frequency') || ! $request->filled('frequency_day')) {
            return;
        }

        $frequency = RecurrenceFrequency::tryFrom((string) $request->input('frequency'));
        $day = (int) $request->input('frequency_day');
        $error = match ($frequency) {
            RecurrenceFrequency::Weekly => $day < 0 || $day > 6,
            RecurrenceFrequency::Monthly => $day < 1 || $day > 31,
            default => false,
        };

        if ($error) {
            $validator->errors()->add('frequency_day', self::frequencyDayErrorMessage($frequency));
        }
    }

    private static function frequencyDayErrorMessage(?RecurrenceFrequency $frequency): string
    {
        return match ($frequency) {
            RecurrenceFrequency::Weekly => 'Dia da semana inválido.',
            RecurrenceFrequency::Monthly => 'Dia do mês inválido.',
            default => 'Dia inválido.',
        };
    }
}
