<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|object>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'color' => ['sometimes', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'expense_group_id' => ['sometimes', 'nullable', Rule::exists('expense_groups', 'uuid')->where(
                'workspace_id',
                $this->workspaceId(),
            )],
        ];
    }

    private function workspaceId(): string
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace ? (string) $workspace->id : '';
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'color.size' => 'Cor inválida. Use o formato #RRGGBB.',
            'color.regex' => 'Cor inválida. Use o formato #RRGGBB.',
            'expense_group_id.exists' => 'O grupo de despesa selecionado é inválido.',
        ];
    }
}
