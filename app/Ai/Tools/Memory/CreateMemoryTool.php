<?php

declare(strict_types=1);

namespace App\Ai\Tools\Memory;

use App\Enums\MemoryType;
use App\Models\Workspace;
use App\Services\AgentMemoryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class CreateMemoryTool implements Tool
{
    public function __construct(protected string $workspaceUuid) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Create a new memory in the workspace. Memories persist contextual knowledge for future agent runs.';
    }

    /**
     * Execute the tool.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function handle(Request $request): string
    {
        $type = (string) $request->string('type');

        $memory = $this->service()->create($this->resolveWorkspace(), [
            'title' => (string) $request->string('title'),
            'content' => (string) $request->string('content'),
            'type' => $type !== '' ? $type : MemoryType::Insight->value,
            'source' => (string) $request->string('source') ?: null,
            'expires_at' => (string) $request->string('expires_at') ?: null,
        ]);

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
            'title' => $schema->string()
                ->description('Short title summarizing the memory.')
                ->required(),
            'content' => $schema->string()
                ->description('Full content of the memory.')
                ->required(),
            'type' => $schema->string()
                ->enum(['insight', 'pattern', 'anomaly', 'summary'])
                ->description('Memory type. Default: insight.'),
            'source' => $schema->string()
                ->description('Origin of the memory (e.g. tool name or agent run).'),
            'expires_at' => $schema->string()
                ->description('Optional expiration datetime (Y-m-d H:i:s).'),
        ];
    }
}
