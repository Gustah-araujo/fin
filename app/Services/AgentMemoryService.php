<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgentMemory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

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
        $memories = $this->list($workspace);

        if ($memories->isEmpty()) {
            return 'Nenhuma memória registrada.';
        }

        return $memories->map(function (AgentMemory $memory, int $index): string {
            return sprintf(
                "[%d] (%s) %s\n    %s",
                $index + 1,
                $memory->type->label(),
                $memory->title,
                $memory->content,
            );
        })->implode("\n");
    }
}
