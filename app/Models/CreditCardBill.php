<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillStatus;
use Database\Factories\CreditCardBillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $credit_card_id
 * @property int $workspace_id
 * @property int $period_year
 * @property int $period_month
 * @property Carbon $closing_date
 * @property Carbon $due_date
 * @property BillStatus $status
 * @property numeric-string|float|int $total_amount
 * @property Carbon|null $closed_at
 * @property Carbon|null $paid_at
 * @property int|null $paid_to_account_id
 * @property int|null $payment_transaction_id
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at */
class CreditCardBill extends Model
{
    /** @use HasFactory<CreditCardBillFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'credit_card_id',
        'workspace_id',
        'period_year',
        'period_month',
        'closing_date',
        'due_date',
        'status',
        'total_amount',
        'closed_at',
        'paid_at',
        'paid_to_account_id',
        'payment_transaction_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'closing_date' => 'date',
            'due_date' => 'date',
            'status' => BillStatus::class,
            'total_amount' => 'decimal:2',
            'closed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<CreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'credit_card_bill_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_to_account_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_transaction_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
