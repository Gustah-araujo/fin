<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgentMemory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AgentMemoryService
{
    public function create(Workspace $workspace, array $data): AgentMemory
    {
        return $workspace->agentMemories()->create($data);
    }

    public function list(Workspace $workspace): Collection
    {
        return $workspace->agentMemories()->active()->latest()->get();
    }

    public function find(Workspace $workspace, string $uuid): AgentMemory
    {
        return $workspace->agentMemories()->active()->where('uuid', $uuid)->firstOrFail();
    }

    public function update(AgentMemory $memory, array $data): AgentMemory
    {
        $memory->update($data);

        return $memory->fresh();
    }

    public function delete(AgentMemory $memory): void
    {
        $memory->delete();
    }

    public function formatForPrompt(Workspace $workspace): string
    {
        $memories = $this->list($workspace)
            ->take((int) config('ai.memories.prompt_limit', 20));

        if ($memories->isEmpty()) {
            return 'Nenhuma memória registrada.';
        }

        $maxChars = (int) config('ai.memories.content_max_chars', 400);

        return $memories->map(function (AgentMemory $memory, int $index) use ($maxChars): string {
            return sprintf(
                "[%d] (%s) %s\n    %s",
                $index + 1,
                $memory->type->label(),
                Str::limit($memory->title, $maxChars),
                Str::limit($memory->content, $maxChars),
            );
        })->implode("\n");
    }
}
