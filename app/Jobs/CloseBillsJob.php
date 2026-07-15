<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BillService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CloseBillsJob implements ShouldQueue
{
    use Queueable;

    public function handle(BillService $billService): void
    {
        try {
            $billService->closeBillsBefore(Carbon::now());
        } catch (\Exception $e) {
            Log::error('CloseBillsJob failed: ' . $e->getMessage());
        }
    }
}
