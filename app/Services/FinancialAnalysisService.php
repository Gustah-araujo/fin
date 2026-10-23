<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\ScheduledFinancialAnalysisAgent;
use App\Models\Workspace;
use App\Notifications\Workspace\FinancialInsightNotification;
use Laravel\Ai\Responses\StructuredAgentResponse;

class FinancialAnalysisService
{
    /**
     * Run the scheduled financial analysis for a workspace, persisting
     * insights as agent memories and notifying members when any exist.
     *
     * @return int Number of insights processed
     */
    public function analyze(Workspace $workspace): int
    {
        $agent = new ScheduledFinancialAnalysisAgent($workspace);
        $response = $agent->prompt('Analise as finanças recentes do workspace e identifique insights.');

        $data = $response instanceof StructuredAgentResponse ? $response->structured : $response;
        $insights = $data['insights'] ?? [];

        foreach ($insights as $insight) {
            app(AgentMemoryService::class)->create($workspace, [
                'title' => $insight['title'],
                'content' => $insight['description'],
                'type' => $insight['type'],
                'source' => ScheduledFinancialAnalysisAgent::class,
            ]);
        }

        if (count($insights) > 0) {
            $workspace->members->each->notify(new FinancialInsightNotification($workspace, count($insights)));
        }

        return count($insights);
    }
}
