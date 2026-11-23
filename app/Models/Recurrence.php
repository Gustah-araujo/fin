<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use Database\Factories\RecurrenceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property int|null $account_id
 * @property string|null $credit_card_id
 * @property int $category_id
 * @property TransactionType $type
 * @property string $description
 * @property numeric-string|float|int $value
 * @property RecurrenceFrequency $frequency
 * @property int $frequency_day
 * @property Carbon $start_date
 * @property Carbon|null $until_date
 * @property Carbon|null $next_date
 * @property RecurrenceStatus $status
 * @property int $buffer_ahead
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at */
class Recurrence extends Model
{
    /** @use HasFactory<RecurrenceFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'account_id',
        'credit_card_id',
        'category_id',
        'type',
        'description',
        'value',
        'frequency',
        'frequency_day',
        'start_date',
        'until_date',
        'next_date',
        'status',
        'buffer_ahead',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'frequency' => RecurrenceFrequency::class,
            'status' => RecurrenceStatus::class,
            'value' => 'decimal:2',
            'start_date' => 'date',
            'until_date' => 'date',
            'next_date' => 'date',
            'buffer_ahead' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
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
        return $this->belongsTo(CreditCard::class, 'credit_card_id', 'uuid');
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
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
