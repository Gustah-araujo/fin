<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\ScheduledFinancialAnalysisAgent;
use App\Models\User;
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

        $structured = $response instanceof StructuredAgentResponse
            ? $response->structured
            : [];

        $rawInsights = $structured['insights'] ?? [];
        $insights = is_array($rawInsights) ? $rawInsights : [];

        foreach ($insights as $insight) {
            if (! is_array($insight)) {
                continue;
            }

            app(AgentMemoryService::class)->create($workspace, [
                'title' => $insight['title'] ?? null,
                'content' => $insight['description'] ?? null,
                'type' => $insight['type'] ?? null,
                'source' => ScheduledFinancialAnalysisAgent::class,
            ]);
        }

        if (count($insights) > 0) {
            $workspace->members->each(
                fn (User $member) => $member->notify(new FinancialInsightNotification($workspace, count($insights)))
            );
        }

        return count($insights);
    }
}
