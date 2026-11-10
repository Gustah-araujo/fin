<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FinAssistantRun;
use App\Models\User;
use App\Models\Workspace;

class FinAssistantRunPolicy
{
    public function view(User $user, FinAssistantRun $run, Workspace $workspace): bool
    {
        if ((int) $run->workspace_id !== (int) $workspace->id) {
            return false;
        }

        return $workspace->members()->where('user_id', $user->id)->exists();
    }
}
