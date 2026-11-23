<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property int|null $created_by
 * @property string $name
 * @property TransactionType $type
 * @property string $color
 * @property string|null $icon
 * @property int|null $position
 * @property bool $is_system
 * @property int|null $expense_group_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'created_by',
        'name',
        'type',
        'color',
        'icon',
        'position',
        'is_system',
        'expense_group_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'is_system' => 'boolean',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<ExpenseGroup, $this>
     */
    public function expenseGroup(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class);
    }
}
