<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RecurrenceStatus;
use App\Models\Recurrence;
use App\Services\RecurrenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessRecurrencesJob implements ShouldQueue
{
    use Queueable;

    public function handle(RecurrenceService $service): void
    {
        $recurrences = Recurrence::whereNull('deleted_at')
            ->where('status', RecurrenceStatus::Active)
            ->get();

        foreach ($recurrences as $recurrence) {
            try {
                $service->generateBufferInstances($recurrence);
            } catch (Throwable $e) {
                Log::error("Buffer maintenance failed for {$recurrence->uuid}: ".$e->getMessage());
            }
        }
    }
}
