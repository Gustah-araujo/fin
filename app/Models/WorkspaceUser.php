<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Pivot table "workspace_user" linking workspaces to their members.
 *
 * @property int $workspace_id
 * @property int $user_id
 * @property string $role
 * @property Carbon|null $last_visited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkspaceUser extends Pivot
{
    protected $table = 'workspace_user';
}
