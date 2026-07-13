<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Workspace;
use App\Policies\WorkspacePolicy;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        JsonResource::withoutWrapping();
    }

    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
    }
}
