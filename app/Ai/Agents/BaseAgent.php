<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\Memory\CreateMemoryTool;
use App\Ai\Tools\Memory\DeleteMemoryTool;
use App\Ai\Tools\Memory\ReadMemoryTool;
use App\Ai\Tools\Memory\UpdateMemoryTool;
use App\Models\Workspace;
use App\Services\AgentMemoryService;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

abstract class BaseAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(protected Workspace $workspace) {}

    public function provider(): string
    {
        return 'opencode';
    }

    abstract public function instructions(): Stringable|string;

    public function tools(): iterable
    {
        return [
            new CreateMemoryTool($this->workspace->uuid),
            new ReadMemoryTool($this->workspace->uuid),
            new UpdateMemoryTool($this->workspace->uuid),
            new DeleteMemoryTool($this->workspace->uuid),
            ...$this->agentTools(),
        ];
    }

    /**
     * @return iterable<int, Tool>
     */
    abstract protected function agentTools(): iterable;

    protected function memoryMap(): string
    {
        return app(AgentMemoryService::class)->formatForPrompt($this->workspace);
    }
}
