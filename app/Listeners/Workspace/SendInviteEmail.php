<?php

declare(strict_types=1);

namespace App\Listeners\Workspace;

use App\Events\Workspace\InviteCreated;
use App\Models\User;
use App\Notifications\Workspace\NewInvite;

class SendInviteEmail
{
    public function handle(InviteCreated $event): void
    {
        $targetUser = User::where('email', $event->invite->email)->first();

        if ($targetUser) {
            $targetUser->notify(new NewInvite(
                $event->invite,
                $event->inviter,
            ));
        }
    }
}
