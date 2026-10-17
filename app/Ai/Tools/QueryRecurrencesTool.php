<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use App\Models\Recurrence;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class QueryRecurrencesTool implements Tool
{
    public function __construct(
        protected string $workspaceId,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Query recurring transactions in the workspace. Returns uuid, description, value, type, frequency, status, start_date, and next_date. Use this to check if a transaction matches a known recurrence pattern.';
    }

    /**
     * Execute the tool.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function handle(Request $request): string
    {
        $query = Recurrence::query()
            ->where('workspace_id', $this->workspaceId);

        $type = (string) $request->string('type');
        if ($type !== '') {
            $query->where('type', TransactionType::from($type));
        }

        $status = (string) $request->string('status');
        if ($status !== '') {
            $query->where('status', RecurrenceStatus::from($status));
        }

        $recurrences = $query
            ->orderBy('next_date', 'asc')
            ->get(['uuid', 'description', 'value', 'type', 'frequency', 'status', 'start_date', 'next_date']);

        return json_encode([
            'count' => $recurrences->count(),
            'recurrences' => $recurrences->map(fn (Recurrence $r): array => [
                'uuid' => $r->uuid,
                'description' => $r->description,
                'value' => (float) $r->value,
                'type' => $r->type->value,
                'frequency' => $r->frequency->value,
                'status' => $r->status->value,
                'start_date' => $r->start_date->format('Y-m-d'),
                'next_date' => $r->next_date?->format('Y-m-d'),
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
        return [
            'type' => $schema->string()
                ->enum(['expense', 'income'])
                ->description('Filter by transaction type.'),
            'status' => $schema->string()
                ->enum(['active', 'paused'])
                ->description('Filter by recurrence status.'),
        ];
    }
}
