<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'type',
        'description',
        'value',
        'date',
        'installment_number',
        'installments_total',
        'installment_group_id',
        'is_recurring',
        'recurring_parent_uuid',
        'recurring_ends_at',
        'recurring_year_month',
        'transfer_group_id',
        'paid_at',
        'created_by',
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
            'is_recurring' => 'boolean',
            'recurring_ends_at' => 'date',
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

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function recurringParent(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'recurring_parent_uuid', 'uuid');
    }

    public function recurringOccurrences(): HasMany
    {
        return $this->hasMany(Transaction::class, 'recurring_parent_uuid', 'uuid');
    }

    public function transferSiblings(): HasMany
    {
        return $this->hasMany(Transaction::class, 'transfer_group_id', 'transfer_group_id')
            ->whereKeyNot($this->id);
    }

    public function isTransfer(): bool
    {
        return $this->transfer_group_id !== null;
    }

    public function isRecurringTemplate(): bool
    {
        return $this->is_recurring && $this->recurring_parent_uuid === null;
    }

    public function isRecurringOccurrence(): bool
    {
        return $this->recurring_parent_uuid !== null;
    }
}
