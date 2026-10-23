<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\FinancialAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunFinancialAnalysisCommand extends Command
{
    protected $signature = 'agent:financial-analysis';

    protected $description = 'Run scheduled financial analysis for all workspaces';

    public function handle(FinancialAnalysisService $service): int
    {
        $workspaces = Workspace::query()->get();

        foreach ($workspaces as $workspace) {
            try {
                $service->analyze($workspace);
            } catch (Throwable $e) {
                Log::error("Financial analysis failed for workspace {$workspace->uuid}: ".$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
