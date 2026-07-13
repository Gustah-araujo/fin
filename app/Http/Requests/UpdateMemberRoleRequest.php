<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "role" => ["required", "string", "in:admin,editor,viewer"],
        ];
    }

    public function messages(): array
    {
        return [
            "role.required" => "O papel é obrigatório.",
            "role.in" => "Papel inválido.",
        ];
    }
}
