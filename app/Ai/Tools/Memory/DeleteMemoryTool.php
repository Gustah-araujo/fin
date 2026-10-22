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
class DeleteMemoryTool implements Tool
{
    public function __construct(protected string $workspaceUuid) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Delete a memory by uuid.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $uuid = (string) $request->string('uuid');
        $memory = $this->service()->find($this->resolveWorkspace(), $uuid);

        $this->service()->delete($memory);

        return json_encode([
            'deleted' => $uuid,
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
                ->description('UUID of the memory to delete.')
                ->required(),
        ];
    }
}
