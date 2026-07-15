<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BillStatus;
use App\Models\Account;
use App\Models\CreditCardBill;
use Illuminate\Foundation\Http\FormRequest;

class PayBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:accounts,uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->route('workspace');
            $bill = $this->route('bill');

            if ($this->filled('account_id')) {
                $account = Account::where('uuid', $this->input('account_id'))
                    ->where('workspace_id', $workspace->id)
                    ->first();

                if (! $account) {
                    $validator->errors()->add('account_id', 'A conta selecionada não pertence a este workspace.');
                    return;
                }

                if ($account->trashed()) {
                    $validator->errors()->add('account_id', 'A conta selecionada foi arquivada.');
                }
            }

            if ($bill && $bill->status === BillStatus::Open) {
                $validator->errors()->add('bill', 'A fatura ainda está aberta. Encerre o ciclo antes de pagar.');
            }

            if ($bill && $bill->status === BillStatus::Paid) {
                $validator->errors()->add('bill', 'Esta fatura já foi paga.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta é obrigatória.',
            'account_id.exists' => 'A conta selecionada é inválida.',
        ];
    }
}
