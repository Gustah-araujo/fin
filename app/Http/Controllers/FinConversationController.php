<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinConversationRequest;
use App\Http\Requests\UpdateFinConversationRequest;
use App\Http\Resources\FinConversationResource;
use App\Models\Workspace;
use App\Services\FinAssistantService;
use Illuminate\Http\JsonResponse;
use Laravel\Ai\Models\Conversation;

class FinConversationController extends Controller
{
    public function index(Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [Conversation::class, $workspace]);

        $conversations = Conversation::query()
            ->where('participant_type', Workspace::class)
            ->where('participant_id', $workspace->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(FinConversationResource::collection($conversations));
    }

    public function store(StoreFinConversationRequest $request, Workspace $workspace, FinAssistantService $service): JsonResponse
    {
        $this->authorize('create', [Conversation::class, $workspace]);

        $title = $request->validated('title') ?: 'Nova conversa';

        $conversation = $service->createConversation($workspace, $request->user(), $title);

        return response()->json(new FinConversationResource($conversation), 201);
    }

    public function show(Workspace $workspace, Conversation $conversation): JsonResponse
    {
        abort_if(
            $conversation->participant_type !== Workspace::class || (int) $conversation->participant_id !== (int) $workspace->id,
            404
        );

        $this->authorize('view', [$conversation, $workspace]);

        $conversation->setRelation('messages', $conversation->messages()->orderBy('id')->get());

        return response()->json(new FinConversationResource($conversation));
    }

    public function update(UpdateFinConversationRequest $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        abort_if(
            $conversation->participant_type !== Workspace::class || (int) $conversation->participant_id !== (int) $workspace->id,
            404
        );

        $this->authorize('update', [$conversation, $workspace]);

        $conversation->update(['title' => $request->validated('title')]);

        return response()->json(new FinConversationResource($conversation));
    }

    public function destroy(Workspace $workspace, Conversation $conversation): JsonResponse
    {
        abort_if(
            $conversation->participant_type !== Workspace::class || (int) $conversation->participant_id !== (int) $workspace->id,
            404
        );

        $this->authorize('delete', [$conversation, $workspace]);

        $conversation->messages()->delete();
        $conversation->delete();

        return response()->json(null, 204);
    }
}
