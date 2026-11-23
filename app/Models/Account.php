<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AccountFactory;
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
 * @property string $type
 * @property float $initial_balance
 * @property float $current_balance
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'created_by',
        'name',
        'type',
        'initial_balance',
        'current_balance',
    ];

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
        return $this->hasMany(Transaction::class);
    }
}
