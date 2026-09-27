<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CreditCard;
use App\Services\CreditCardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PreCreateBillsJob implements ShouldQueue
{
    use Queueable;

    public function handle(CreditCardService $creditCardService): void
    {
        CreditCard::whereNull('deleted_at')->chunk(100, function ($cards) use ($creditCardService) {
            foreach ($cards as $card) {
                try {
                    $creditCardService->preCreateBills($card);
                } catch (\Exception $e) {
                    Log::error("Failed to pre-create bills for card {$card->uuid}: {$e->getMessage()}");
                    // Continue with next card (resilience pattern)
                }
            }
        });
    }
}
