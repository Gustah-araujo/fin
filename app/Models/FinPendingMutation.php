<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinPendingMutationStatus;
use App\Enums\TransactionType;
use Database\Factories\FinPendingMutationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property string $conversation_id
 * @property int $user_id
 * @property TransactionType $type
 * @property array<array-key, mixed> $payload
 * @property FinPendingMutationStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at */
class FinPendingMutation extends Model
{
    /** @use HasFactory<FinPendingMutationFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'conversation_id',
        'user_id',
        'type',
        'payload',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'payload' => 'array',
            'status' => FinPendingMutationStatus::class,
            'expires_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
