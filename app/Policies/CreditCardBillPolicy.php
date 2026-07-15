<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\CreditCardBill;
use App\Models\User;
use App\Models\Workspace;

class CreditCardBillPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, CreditCardBill $bill, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    public function pay(User $user, CreditCardBill $bill, Workspace $workspace): bool
    {
        return $this->hasWriteAccess($user, $workspace);
    }

    public function unpay(User $user, CreditCardBill $bill, Workspace $workspace): bool
    {
        return $this->hasWriteAccess($user, $workspace);
    }

    private function hasWriteAccess(User $user, Workspace $workspace): bool
    {
        $role = $this->getUserRole($user, $workspace);

        return $role === WorkspaceRole::Admin || $role === WorkspaceRole::Editor;
    }

    private function getUserRole(User $user, Workspace $workspace): ?WorkspaceRole
    {
        $pivot = $workspace->members()->where('user_id', $user->id)->first();

        if (! $pivot || ! $pivot->pivot->role) {
            return null;
        }

        return WorkspaceRole::from($pivot->pivot->role);
    }
}
