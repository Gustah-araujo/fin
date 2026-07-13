<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegisteredUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "name" => ["required", "string", "max:255"],
            "email" => ["required", "string", "email", "max:255", "unique:users"],
            "password" => ["required", "string", "min:8", "confirmed"],
        ];
    }

    public function messages(): array
    {
        return [
            "name.required" => "O nome é obrigatório.",
            "email.required" => "O email é obrigatório.",
            "email.email" => "Informe um email válido.",
            "email.unique" => "Este email já está em uso.",
            "password.required" => "A senha é obrigatória.",
            "password.min" => "A senha deve ter no mínimo 8 caracteres.",
            "password.confirmed" => "A confirmação de senha não confere.",
        ];
    }
}
