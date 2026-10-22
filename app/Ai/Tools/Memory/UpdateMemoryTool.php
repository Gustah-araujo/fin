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
class UpdateMemoryTool implements Tool
{
    public function __construct(protected string $workspaceUuid) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return "Update an existing memory's title, content, or type by uuid.";
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $uuid = (string) $request->string('uuid');
        $memory = $this->service()->find($this->resolveWorkspace(), $uuid);

        $data = array_filter([
            'title' => (string) $request->string('title'),
            'content' => (string) $request->string('content'),
            'type' => (string) $request->string('type'),
        ], fn (string $value): bool => $value !== '');

        $memory = $this->service()->update($memory, $data);

        return json_encode([
            'uuid' => $memory->uuid,
            'title' => $memory->title,
            'type' => $memory->type->value,
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
                ->description('UUID of the memory to update.')
                ->required(),
            'title' => $schema->string()
                ->description('New title for the memory.'),
            'content' => $schema->string()
                ->description('New content for the memory.'),
            'type' => $schema->string()
                ->enum(['insight', 'pattern', 'anomaly', 'summary'])
                ->description('New type for the memory.'),
        ];
    }
}
