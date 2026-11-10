<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinAssistantRun extends Model
{
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

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
