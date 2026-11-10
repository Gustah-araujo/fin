<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use Laravel\Ai\Models\Conversation;

class FinConversationPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->isMember($user, $workspace);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->isMember($user, $workspace);
    }

    public function view(User $user, Conversation $conversation, Workspace $workspace): bool
    {
        return $this->isMember($user, $workspace);
    }

    public function update(User $user, Conversation $conversation, Workspace $workspace): bool
    {
        return $this->isMember($user, $workspace);
    }

    public function delete(User $user, Conversation $conversation, Workspace $workspace): bool
    {
        return $this->isMember($user, $workspace);
    }

    private function isMember(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }
}
