<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        "uuid",
        "name",
        "description",
    ];

    public function getRouteKeyName(): string
    {
        return "uuid";
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, "workspace_user")
            ->withPivot("role", "last_visited_at")
            ->withTimestamps();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
