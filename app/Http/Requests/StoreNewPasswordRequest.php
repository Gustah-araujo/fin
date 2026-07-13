<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "token" => ["required"],
            "email" => ["required", "string", "email"],
            "password" => ["required", "string", "min:8", "confirmed"],
        ];
    }

    public function messages(): array
    {
        return [
            "token.required" => "Token inválido.",
            "email.required" => "O email é obrigatório.",
            "email.email" => "Informe um email válido.",
            "password.required" => "A senha é obrigatória.",
            "password.min" => "A senha deve ter no mínimo 8 caracteres.",
            "password.confirmed" => "A confirmação de senha não confere.",
        ];
    }
}
