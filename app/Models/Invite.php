<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InviteStatus;
use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invite extends Model
{
    use HasFactory;

    protected $fillable = [
        "uuid",
        "workspace_id",
        "email",
        "role",
        "inviter_id",
        "status",
    ];

    protected function casts(): array
    {
        return [
            "role" => WorkspaceRole::class,
            "status" => InviteStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return "uuid";
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, "inviter_id");
    }
}
