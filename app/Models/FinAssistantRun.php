<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinRunStatus;
use Database\Factories\FinAssistantRunFactory;
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
 * @property FinRunStatus $status
 * @property string|null $error_message
 * @property string|null $result_message_id
 * @property Carbon $created_at
 * @property Carbon $updated_at */
class FinAssistantRun extends Model
{
    /** @use HasFactory<FinAssistantRunFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'conversation_id',
        'user_id',
        'status',
        'error_message',
        'result_message_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => FinRunStatus::class,
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
