<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\CreditCard;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class QueryCreditCardsTool implements Tool
{
    public function __construct(protected string $workspaceUuid) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Query all credit cards in the workspace. Returns uuid, name, credit_limit, available_limit, closing_day, and due_day for each card.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $workspace = Workspace::where('uuid', $this->workspaceUuid)->firstOrFail();

        $creditCards = CreditCard::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['uuid', 'name', 'credit_limit', 'available_limit', 'closing_day', 'due_day']);

        return json_encode([
            'count' => $creditCards->count(),
            'credit_cards' => $creditCards->map(fn (CreditCard $c): array => [
                'uuid' => $c->uuid,
                'name' => $c->name,
                'credit_limit' => (float) $c->credit_limit,
                'available_limit' => (float) $c->available_limit,
                'closing_day' => (int) $c->closing_day,
                'due_day' => (int) $c->due_day,
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
