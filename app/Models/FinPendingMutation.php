<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinPendingMutationStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinPendingMutation extends Model
{
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

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
