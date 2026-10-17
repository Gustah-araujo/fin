<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AiParserInterface;
use App\Models\Recurrence;
use App\Models\Workspace;
use App\Policies\RecurrencePolicy;
use App\Policies\WorkspacePolicy;
use App\Services\AiService;
use App\Services\FakeAiService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        JsonResource::withoutWrapping();

        $this->app->bind(AiParserInterface::class, function () {
            if (! app()->environment('production')) {
                return new FakeAiService;
            }

            return new AiService;
        });
    }

    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Recurrence::class, RecurrencePolicy::class);
    }
}
