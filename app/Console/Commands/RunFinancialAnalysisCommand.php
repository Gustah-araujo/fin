<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\ScheduledFinancialAnalysisAgent;
use App\Models\Workspace;
use App\Notifications\Workspace\FinancialInsightNotification;
use App\Services\AgentMemoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class RunFinancialAnalysisCommand extends Command
{
    protected $signature = 'agent:financial-analysis';

    protected $description = 'Run scheduled financial analysis for all workspaces';

    public function handle(AgentMemoryService $service): int
    {
        $workspaces = Workspace::query()->get();

        foreach ($workspaces as $workspace) {
            try {
                $this->analyzeWorkspace($workspace, $service);
            } catch (Throwable $e) {
                Log::error("Financial analysis failed for workspace {$workspace->uuid}: ".$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    private function analyzeWorkspace(Workspace $workspace, AgentMemoryService $service): void
    {
        $agent = new ScheduledFinancialAnalysisAgent($workspace);
        $response = $agent->prompt('Analise as finanças recentes do workspace e identifique insights.');

        $data = $response instanceof StructuredAgentResponse ? $response->structured : $response;
        $insights = $data['insights'] ?? [];

        foreach ($insights as $insight) {
            $service->create($workspace, [
                'title' => $insight['title'],
                'content' => $insight['description'],
                'type' => $insight['type'],
                'source' => ScheduledFinancialAnalysisAgent::class,
            ]);
        }

        if (count($insights) > 0) {
            $notification = new FinancialInsightNotification($workspace, count($insights));
            $workspace->members->each->notify($notification);
        }
    }
}
