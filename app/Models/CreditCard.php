<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CreditCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property int|null $created_by
 * @property string $name
 * @property numeric-string|float|int $credit_limit
 * @property numeric-string|float|int $available_limit
 * @property int $closing_day
 * @property int $due_day
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at */
class CreditCard extends Model
{
    /** @use HasFactory<CreditCardFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'created_by',
        'name',
        'credit_limit',
        'available_limit',
        'closing_day',
        'due_day',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'available_limit' => 'decimal:2',
            'closing_day' => 'integer',
            'due_day' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'credit_card_id');
    }

    /**
     * @return HasMany<CreditCardBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(CreditCardBill::class);
    }
}
