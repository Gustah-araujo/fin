<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Workspace\InviteCreated;
use App\Listeners\Workspace\SendInviteEmail;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        InviteCreated::class => [
            SendInviteEmail::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
