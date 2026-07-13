<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "email" => ["required", "string", "email"],
        ];
    }

    public function messages(): array
    {
        return [
            "email.required" => "O email é obrigatório.",
            "email.email" => "Informe um email válido.",
        ];
    }
}
