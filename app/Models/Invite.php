<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InviteStatus;
use App\Enums\WorkspaceRole;
use Database\Factories\InviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property string $email
 * @property WorkspaceRole $role
 * @property int $inviter_id
 * @property InviteStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at */
class Invite extends Model
{
    /** @use HasFactory<InviteFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'email',
        'role',
        'inviter_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'status' => InviteStatus::class,
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }
}
