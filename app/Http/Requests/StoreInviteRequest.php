<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "email" => ["required", "string", "email"],
            "role" => ["required", "string", "in:admin,editor,viewer"],
        ];
    }

    public function messages(): array
    {
        return [
            "email.required" => "O email é obrigatório.",
            "email.email" => "Informe um email válido.",
            "role.required" => "O papel é obrigatório.",
            "role.in" => "Papel inválido.",
        ];
    }
}
