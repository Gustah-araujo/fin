<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MemoryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMemory extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'title',
        'content',
        'type',
        'source',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MemoryType::class,
            'expires_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
