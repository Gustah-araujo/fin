<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\FinPendingMutationStatus;
use App\Models\FinPendingMutation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\TransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class ConfirmTransactionTool implements Tool
{
    public function __construct(
        protected string $workspaceUuid,
        protected string $conversationId,
        protected int $userId,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Persist a previously validated transaction (income/expense) after the user has explicitly confirmed it. Accepts only a pending_id; re-scopes it to the workspace and delegates persistence to the transaction service.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $workspace = Workspace::where('uuid', $this->workspaceUuid)->firstOrFail();

        $pendingId = (string) $request->string('pending_id');

        $pending = FinPendingMutation::where('uuid', $pendingId)
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($pending === null) {
            return json_encode(['error' => 'Mutação pendente não encontrada neste workspace.']);
        }

        if ($pending->status !== FinPendingMutationStatus::Pending) {
            return json_encode(['error' => 'Essa mutação já não está mais pendente de confirmação.']);
        }

        if ($pending->expires_at !== null && $pending->expires_at->isPast()) {
            $pending->update(['status' => FinPendingMutationStatus::Expired]);

            return json_encode(['error' => 'A confirmação expirou. Inicie novamente o cadastro.']);
        }

        $creator = User::findOrFail($this->userId);

        $payload = $pending->payload;

        $transaction = app(TransactionService::class)->create($workspace, $creator, $payload);

        $pending->update(['status' => FinPendingMutationStatus::Confirmed]);

        return json_encode([
            'transaction_uuid' => $transaction->uuid,
            'type' => $transaction->type->value,
            'description' => $transaction->description,
            'value' => (float) $transaction->value,
            'date' => $transaction->date->format('Y-m-d'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'pending_id' => $schema->string()->required()->description('The pending mutation UUID returned by validate_transaction.'),
        ];
    }
}
