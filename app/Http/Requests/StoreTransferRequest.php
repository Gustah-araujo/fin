<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
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
            'from_account_id' => ['required', 'exists:accounts,uuid'],
            'to_account_id' => ['required', 'exists:accounts,uuid'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->route('workspace');

            $this->validateFromAccount($validator, $workspace);
            $this->validateToAccount($validator, $workspace);
            $this->validateAccountsDiffer($validator);
            $this->validateTags($validator, $workspace);
        });
    }

    private function validateFromAccount($validator, $workspace): void
    {
        if (! $this->filled('from_account_id')) {
            return;
        }

        if ($this->accountBelongsToWorkspace($this->input('from_account_id'), $workspace)) {
            return;
        }

        $validator->errors()->add('from_account_id', 'A conta de origem não pertence a este workspace.');
    }

    private function validateToAccount($validator, $workspace): void
    {
        if (! $this->filled('to_account_id')) {
            return;
        }

        if ($this->accountBelongsToWorkspace($this->input('to_account_id'), $workspace)) {
            return;
        }

        $validator->errors()->add('to_account_id', 'A conta de destino não pertence a este workspace.');
    }

    private function validateAccountsDiffer($validator): void
    {
        if (! $this->filled('from_account_id') || ! $this->filled('to_account_id')) {
            return;
        }

        if ($this->input('from_account_id') !== $this->input('to_account_id')) {
            return;
        }

        $validator->errors()->add('from_account_id', 'Conta de origem e destino devem ser diferentes.');
    }

    private function validateTags($validator, $workspace): void
    {
        if (! $this->filled('tags')) {
            return;
        }

        $tagCount = Tag::whereIn('uuid', $this->input('tags'))
            ->where('workspace_id', $workspace->id)
            ->count();

        if ($tagCount === count($this->input('tags'))) {
            return;
        }

        $validator->errors()->add('tags', 'Uma ou mais tags são inválidas.');
    }

    private function accountBelongsToWorkspace(string $uuid, $workspace): bool
    {
        return Account::where('uuid', $uuid)
            ->where('workspace_id', $workspace->id)
            ->exists();
    }

    public function messages(): array
    {
        return [
            'description.required' => 'A descrição é obrigatória.',
            'value.required' => 'O valor é obrigatório.',
            'value.gt' => 'O valor deve ser maior que zero.',
            'date.required' => 'A data é obrigatória.',
            'from_account_id.required' => 'A conta de origem é obrigatória.',
            'to_account_id.required' => 'A conta de destino é obrigatória.',
        ];
    }
}
