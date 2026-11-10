<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FinRunResource;
use App\Models\FinAssistantRun;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

class FinRunController extends Controller
{
    public function show(Workspace $workspace, FinAssistantRun $run): JsonResponse
    {
        $this->authorize('view', [$run, $workspace]);

        return response()->json(new FinRunResource($run));
    }
}
