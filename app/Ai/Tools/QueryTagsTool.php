<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class QueryTagsTool implements Tool
{
    public function __construct(
        protected string $workspaceId,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Query all available tags in the workspace. Returns uuid, name, and color for each tag. Use this to suggest relevant tags for transactions.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $tags = Tag::query()
            ->where('workspace_id', $this->workspaceId)
            ->orderBy('name')
            ->get(['uuid', 'name', 'color']);

        return json_encode([
            'count' => $tags->count(),
            'tags' => $tags->map(fn (Tag $t): array => [
                'uuid' => $t->uuid,
                'name' => $t->name,
                'color' => $t->color,
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
