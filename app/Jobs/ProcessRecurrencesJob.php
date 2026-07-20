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
        $recurrences = Recurrence::with('account')
            ->whereNull('deleted_at')
            ->where('status', RecurrenceStatus::Active)
            ->whereNotNull('next_date')
            ->whereDate('next_date', '<=', today())
            ->where(function ($q) {
                $q->whereNull('until_date')
                    ->orWhereColumn('next_date', '<=', 'until_date');
            })
            ->get();

        foreach ($recurrences as $recurrence) {
            try {
                $service->generateNextInstance($recurrence);
            } catch (Throwable $e) {
                Log::error("ProcessRecurrencesJob recurrence {$recurrence->uuid}: " . $e->getMessage());
            }
        }
    }
}
