<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransferRequest extends FormRequest
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
            'from_account_id' => ['sometimes', 'required', 'exists:accounts,uuid'],
            'to_account_id' => ['sometimes', 'required', 'exists:accounts,uuid'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->route('workspace');

            $from = $this->input('from_account_id');
            $to = $this->input('to_account_id');

            if ($this->filled('from_account_id')) {
                $exists = Account::where('uuid', $from)
                    ->where('workspace_id', $workspace->id)
                    ->exists();
                if (! $exists) {
                    $validator->errors()->add('from_account_id', 'A conta de origem não pertence a este workspace.');
                }
            }
            if ($this->filled('to_account_id')) {
                $exists = Account::where('uuid', $to)
                    ->where('workspace_id', $workspace->id)
                    ->exists();
                if (! $exists) {
                    $validator->errors()->add('to_account_id', 'A conta de destino não pertence a este workspace.');
                }
            }
            if ($from && $to && $from === $to) {
                $validator->errors()->add('from_account_id', 'Conta de origem e destino devem ser diferentes.');
            }
        });
    }
}
