<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\FinTitleAgent;
use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Models\Conversation;

class TitleConversationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $conversationId, public Workspace $workspace) {}

    public function handle(): void
    {
        $conversation = Conversation::findOrFail($this->conversationId);

        $firstMessage = $conversation->messages()->where('role', 'user')->orderBy('id')->first();

        if ($firstMessage === null) {
            return;
        }

        $response = (new FinTitleAgent($this->workspace))->prompt($firstMessage->content);

        $title = trim((string) $response->text);

        if ($title !== '') {
            $conversation->update(['title' => $title]);
        }
    }
}
