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

        $user = $request->user();

        abort_unless($user !== null, 401);

        $content = $request->validated('content');

        $run = $service->sendMessage(
            $workspace,
            $user,
            $conversation,
            is_string($content) ? $content : null,
            $request->file('attachments', []),
        );

        return response()->json(new FinRunResource($run), 201);
    }
}
