<?php

declare(strict_types=1);

namespace App\Events\Workspace;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InviteCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Invite $invite,
        public readonly User $inviter,
    ) {}
}
