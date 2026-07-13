<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "name" => ["required", "string", "max:255"],
            "description" => ["nullable", "string"],
        ];
    }

    public function messages(): array
    {
        return [
            "name.required" => "O nome do workspace é obrigatório.",
            "name.max" => "O nome deve ter no máximo 255 caracteres.",
        ];
    }
}
