<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FinConversationResource;
use App\Models\Workspace;
use Inertia\Response;
use Laravel\Ai\Models\Conversation;

class FinController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Conversation::class, $workspace]);

        $conversations = Conversation::query()
            ->where('participant_type', Workspace::class)
            ->where('participant_id', $workspace->id)
            ->orderByDesc('updated_at')
            ->get();

        return inertia('Fin/Index', [
            'conversations' => FinConversationResource::collection($conversations),
        ]);
    }
}
