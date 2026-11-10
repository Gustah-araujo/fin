<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FinAssistantRun;
use App\Services\FinAssistantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchFinAssistantJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public FinAssistantRun $run) {}

    public function handle(FinAssistantService $service): void
    {
        $service->run($this->run);
    }
}
