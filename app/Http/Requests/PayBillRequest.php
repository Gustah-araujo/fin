<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BillStatus;
use App\Models\Account;
use App\Models\CreditCardBill;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PayBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:accounts,uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $workspace = $this->route('workspace');
            $bill = $this->route('bill');

            if ($this->filled('account_id') && $workspace instanceof Workspace) {
                $this->validateAccount($validator, $workspace);
            }

            if ($bill instanceof CreditCardBill) {
                $this->validateBillStatus($validator, $bill);
            }
        });
    }

    private function validateAccount(Validator $validator, Workspace $workspace): void
    {
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

    private function validateBillStatus(Validator $validator, CreditCardBill $bill): void
    {
        if ($this->billStatusIs($bill->status, BillStatus::Open)) {
            $validator->errors()->add('bill', 'A fatura ainda está aberta. Encerre o ciclo antes de pagar.');
        }

        if ($this->billStatusIs($bill->status, BillStatus::Paid)) {
            $validator->errors()->add('bill', 'Esta fatura já foi paga.');
        }
    }

    private function billStatusIs(mixed $status, BillStatus $expected): bool
    {
        return $status instanceof BillStatus
            ? $status === $expected
            : $status === $expected->value;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'A conta é obrigatória.',
            'account_id.exists' => 'A conta selecionada é inválida.',
        ];
    }
}
