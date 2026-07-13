<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "name" => ["required", "string", "max:255"],
            "type" => ["required", Rule::enum(AccountType::class)],
            "initial_balance" => ["required", "numeric"],
        ];
    }

    public function messages(): array
    {
        return [
            "name.required" => "O nome da conta é obrigatório.",
            "name.max" => "O nome deve ter no máximo 255 caracteres.",
            "type.required" => "O tipo da conta é obrigatório.",
            "initial_balance.required" => "O saldo inicial é obrigatório.",
            "initial_balance.numeric" => "O saldo inicial deve ser um valor numérico.",
        ];
    }
}
