<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MemoryType;
use Database\Factories\AgentMemoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property int $workspace_id
 * @property string $title
 * @property string $content
 * @property MemoryType $type
 * @property string|null $source
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at */
class AgentMemory extends Model
{
    /** @use HasFactory<AgentMemoryFactory> */
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
     * @param  Builder<AgentMemory>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
