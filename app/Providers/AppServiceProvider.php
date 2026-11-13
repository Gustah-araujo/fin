<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AiParserInterface;
use App\Models\Recurrence;
use App\Models\Workspace;
use App\Policies\FinConversationPolicy;
use App\Policies\RecurrencePolicy;
use App\Policies\WorkspacePolicy;
use App\Services\AiService;
use App\Services\FakeAiService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

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
        Gate::policy(Conversation::class, FinConversationPolicy::class);

        Mail::extend('brevo', function (array $config): TransportInterface {
            return (new BrevoTransportFactory)->create(
                new Dsn('brevo+api', 'default', $config['api_key'])
            );
        });
    }
}
