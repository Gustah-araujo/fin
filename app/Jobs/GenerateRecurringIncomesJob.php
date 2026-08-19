<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\RecurringIncomeService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateRecurringIncomesJob implements ShouldQueue
{
    use Queueable;

    public function handle(RecurringIncomeService $service): void
    {
        try {
            $templates = Transaction::where('is_recurring', true)
                ->whereNull('recurring_parent_uuid')
                ->where(function ($q) {
                    $q->whereNull('recurring_ends_at')
                        ->orWhere('recurring_ends_at', '>=', today());
                })
                ->get();

            foreach ($templates as $template) {
                $service->generateOccurrencesUpTo($template, Carbon::now()->startOfMonth());
            }
        } catch (\Exception $e) {
            Log::error('GenerateRecurringIncomesJob failed: '.$e->getMessage());
        }
    }
}
