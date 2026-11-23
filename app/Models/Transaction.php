<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property int|null $account_id
 * @property int|null $credit_card_id
 * @property int|null $credit_card_bill_id
 * @property int $category_id
 * @property int|null $expense_group_id
 * @property TransactionType $type
 * @property string $description
 * @property numeric-string|float|int $value
 * @property Carbon $date
 * @property int|null $installment_number
 * @property int|null $installments_total
 * @property string|null $installment_group_id
 * @property Carbon|null $paid_at
 * @property int $created_by
 * @property int|null $recurrence_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
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

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<CreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /**
     * @return BelongsTo<CreditCardBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(CreditCardBill::class, 'credit_card_bill_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<ExpenseGroup, $this>
     */
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

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * @return BelongsTo<Recurrence, $this>
     */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }
}
