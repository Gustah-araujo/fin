<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinMessageRequest;
use App\Http\Resources\FinRunResource;
use App\Models\Workspace;
use App\Services\FinAssistantService;
use Illuminate\Http\JsonResponse;
use Laravel\Ai\Models\Conversation;

class FinMessageController extends Controller
{
    public function store(StoreFinMessageRequest $request, Workspace $workspace, Conversation $conversation, FinAssistantService $service): JsonResponse
    {
        abort_if(
            $conversation->participant_type !== Workspace::class || (int) $conversation->participant_id !== (int) $workspace->id,
            404
        );

        $this->authorize('view', [$conversation, $workspace]);

        $run = $service->sendMessage(
            $workspace,
            $request->user(),
            $conversation,
            $request->validated('content'),
            $request->file('attachments', []),
        );

        return response()->json(new FinRunResource($run), 201);
    }
}
