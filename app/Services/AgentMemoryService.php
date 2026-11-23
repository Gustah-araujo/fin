<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MemoryType;
use App\Models\AgentMemory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AgentMemoryService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data): AgentMemory
    {
        return $workspace->agentMemories()->create($data);
    }

    /**
     * @return Collection<int, AgentMemory>
     */
    public function list(Workspace $workspace): Collection
    {
        return AgentMemory::where('workspace_id', $workspace->id)
            ->active()
            ->latest()
            ->get();
    }

    public function find(Workspace $workspace, string $uuid): AgentMemory
    {
        return AgentMemory::where('workspace_id', $workspace->id)
            ->active()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AgentMemory $memory, array $data): AgentMemory
    {
        $memory->update($data);

        return $memory->fresh() ?? $memory;
    }

    public function delete(AgentMemory $memory): void
    {
        $memory->delete();
    }

    public function formatForPrompt(Workspace $workspace): string
    {
        $memories = $this->list($workspace)
            ->take(self::intConfig('ai.memories.prompt_limit', 20));

        if ($memories->isEmpty()) {
            return 'Nenhuma memória registrada.';
        }

        $maxChars = self::intConfig('ai.memories.content_max_chars', 400);

        return $memories->map(function (AgentMemory $memory, int $index) use ($maxChars): string {
            return sprintf(
                "[%d] (%s) %s\n    %s",
                $index + 1,
                self::memoryTypeLabel($memory),
                Str::limit((string) $memory->title, $maxChars),
                Str::limit((string) $memory->content, $maxChars),
            );
        })->implode("\n");
    }

    private static function memoryTypeLabel(AgentMemory $memory): string
    {
        $type = $memory->getAttribute('type');

        if ($type instanceof MemoryType) {
            return $type->label();
        }

        return is_string($type) ? $type : '';
    }

    private static function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : 0;
    }
}
