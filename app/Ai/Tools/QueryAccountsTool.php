<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Account;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class QueryAccountsTool implements Tool
{
    public function __construct(protected string $workspaceUuid) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Query all accounts in the workspace. Returns uuid, name, type, and current_balance for each account. Use this to find the correct account_uuid for transactions.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $workspace = Workspace::where('uuid', $this->workspaceUuid)->firstOrFail();

        $accounts = Account::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['uuid', 'name', 'type', 'current_balance']);

        return json_encode([
            'count' => $accounts->count(),
            'accounts' => $accounts->map(fn (Account $a): array => [
                'uuid' => $a->uuid,
                'name' => $a->name,
                'type' => $a->type,
                'current_balance' => (float) $a->current_balance,
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
