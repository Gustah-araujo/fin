<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurrenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ApplyRecurrenceScopeChangeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $operation, // 'update' or 'delete'
        public string $transactionUuid,
        public array $payload = [],
        public int $userId = 0,
    ) {}

    public function handle(RecurrenceService $service): void
    {
        /** @var Transaction $transaction */
        $transaction = Transaction::with('recurrence')
            ->where('uuid', $this->transactionUuid)
            ->firstOrFail();

        if ($this->operation === 'update') {
            $user = User::findOrFail($this->userId);
            $service->applyUpdateThisAndFuture($transaction, $this->payload, $user);
        } elseif ($this->operation === 'delete') {
            $service->applyDeleteThisAndFuture($transaction);
        }
    }
}
