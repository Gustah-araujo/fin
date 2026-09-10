<?php

declare(strict_types=1);

namespace App\Providers;

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
    }

    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Recurrence::class, RecurrencePolicy::class);

        $this->app->singleton('ai', function () {
            if (app()->environment('testing') || empty(config('ai.deepseek_key'))) {
                return new FakeAiService;
            }

            return new AiService(
                apiKey: config('ai.deepseek_key'),
                baseUrl: config('ai.base_url'),
                model: config('ai.model'),
            );
        });
    }
}
