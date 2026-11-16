<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'account_id',
        'credit_card_id',
        'credit_card_bill_id',
        'category_id',
        'expense_group_id',
        'type',
        'description',
        'value',
        'date',
        'installment_number',
        'installments_total',
        'installment_group_id',
        'paid_at',
        'created_by',
        'recurrence_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'value' => 'decimal:2',
            'date' => 'date',
            'paid_at' => 'datetime',
            'installment_number' => 'integer',
            'installments_total' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(CreditCardBill::class, 'credit_card_bill_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expenseGroup(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class);
    }

    public function effectiveExpenseGroup(): ?ExpenseGroup
    {
        if ($this->expense_group_id !== null) {
            return $this->relationLoaded('expenseGroup')
                ? $this->expenseGroup
                : $this->expenseGroup()->first();
        }

        $category = $this->relationLoaded('category')
            ? $this->category
            : $this->category()->first();

        if ($category === null) {
            return null;
        }

        return $category->relationLoaded('expenseGroup')
            ? $category->expenseGroup
            : $category->expenseGroup()->first();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }
}
