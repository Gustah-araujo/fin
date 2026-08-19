<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'value' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:999999999.99'],
            'account_id' => ['sometimes', 'required', 'exists:accounts,uuid'],
            'category_id' => ['sometimes', 'required', 'exists:categories,uuid'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'exists:tags,uuid'],
            'propagate' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->route('workspace');

            if ($this->filled('category_id')) {
                $category = Category::where('uuid', $this->input('category_id'))->first();
                if ($category && $category->type === TransactionType::Expense) {
                    $validator->errors()->add('category_id', 'Categoria de despesa não pode ser usada em receita.');
                }
            }
        });
    }
}
