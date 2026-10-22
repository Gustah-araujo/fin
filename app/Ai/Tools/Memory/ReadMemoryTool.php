<?php

declare(strict_types=1);

namespace App\Ai\Tools\Memory;

use App\Models\Workspace;
use App\Services\AgentMemoryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class ReadMemoryTool implements Tool
{
    public function __construct(protected string $workspaceUuid) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Read memories from the workspace. Returns all active memories, or a single memory when uuid is provided.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $workspace = $this->resolveWorkspace();
        $uuid = (string) $request->string('uuid');

        if ($uuid !== '') {
            $memories = collect([$this->service()->find($workspace, $uuid)]);
        } else {
            $memories = $this->service()->list($workspace);
        }

        return json_encode([
            'count' => $memories->count(),
            'memories' => $memories->map(fn ($memory): array => [
                'uuid' => $memory->uuid,
                'title' => $memory->title,
                'content' => $memory->content,
                'type' => $memory->type->value,
                'source' => $memory->source,
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function resolveWorkspace(): Workspace
    {
        return Workspace::where('uuid', $this->workspaceUuid)->firstOrFail();
    }

    private function service(): AgentMemoryService
    {
        return app(AgentMemoryService::class);
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'uuid' => $schema->string()
                ->description('UUID of a specific memory to fetch. Omit to list all active memories.'),
        ];
    }
}
