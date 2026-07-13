<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        "uuid",
        "workspace_id",
        "created_by",
        "name",
        "type",
        "initial_balance",
        "current_balance",
    ];

    public function getRouteKeyName(): string
    {
        return "uuid";
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }
}
