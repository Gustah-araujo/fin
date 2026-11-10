<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\FinancialAssistantAgent;
use App\Enums\FinRunStatus;
use App\Jobs\DispatchFinAssistantJob;
use App\Jobs\TitleConversationJob;
use App\Models\FinAssistantRun;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

class FinAssistantService
{
    /**
     * Create a new conversation for the given workspace.
     */
    public function createConversation(Workspace $workspace, User $user, string $title): Conversation
    {
        return Conversation::create([
            'id' => Str::orderedUuid()->toString(),
            'participant_type' => Conversation::participantType($workspace),
            'participant_id' => Conversation::participantKey($workspace),
            'title' => $title,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Persist the user message, create a pending run, and dispatch the required jobs.
     */
    public function sendMessage(Workspace $workspace, User $user, Conversation $conversation, string $content): FinAssistantRun
    {
        $isFirstMessage = $conversation->messages()->where('role', 'user')->count() === 0;

        $this->persistUserMessage($conversation, $workspace, $content);

        $run = FinAssistantRun::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'status' => FinRunStatus::Pending,
        ]);

        if ($isFirstMessage) {
            TitleConversationJob::dispatch($conversation->id, $workspace);
        }

        DispatchFinAssistantJob::dispatch($run);

        return $run;
    }

    /**
     * Execute the assistant run, prompting the agent and persisting the response.
     *
     * @throws \Throwable
     */
    public function run(FinAssistantRun $run): void
    {
        $run->update(['status' => FinRunStatus::Processing]);

        try {
            $workspace = Workspace::findOrFail($run->workspace_id);
            $conversation = Conversation::findOrFail($run->conversation_id);

            $history = $this->buildHistory($conversation);

            $promptContent = '';
            $historyMessages = [];

            foreach ($history as $index => $message) {
                if ($message->role === MessageRole::User) {
                    $promptContent = (string) $message->content;
                    $historyMessages = array_slice($history, 0, $index);
                }
            }

            $agent = new FinancialAssistantAgent($workspace, $conversation->id, $run->user_id);
            $response = $agent->withMessages($historyMessages)->prompt($promptContent);

            $assistantMessage = $this->persistAssistantMessage($conversation, $workspace, $response->text);

            $run->update([
                'status' => FinRunStatus::Completed,
                'result_message_id' => $assistantMessage->id,
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => FinRunStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the recent completed-message history for a conversation, oldest first.
     *
     * @return array<int, Message>
     */
    public function buildHistory(Conversation $conversation, ?int $limit = null): array
    {
        $limit = $limit ?? (int) config('ai.fin.context_window');

        return $conversation->messages()
            ->where('status', MessageStatus::Completed->value)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ConversationMessage $message): Message => new Message($message->role, $message->content))
            ->all();
    }

    /**
     * Persist a user message in the conversation.
     */
    public function persistUserMessage(Conversation $conversation, Workspace $workspace, string $content): ConversationMessage
    {
        return $this->persistMessage($conversation, $workspace, 'user', $content);
    }

    /**
     * Persist an assistant message in the conversation.
     */
    public function persistAssistantMessage(Conversation $conversation, Workspace $workspace, string $content): ConversationMessage
    {
        return $this->persistMessage($conversation, $workspace, 'assistant', $content);
    }

    /**
     * Persist a message row in the conversation.
     */
    private function persistMessage(Conversation $conversation, Workspace $workspace, string $role, string $content): ConversationMessage
    {
        return ConversationMessage::create([
            'id' => Str::orderedUuid()->toString(),
            'conversation_id' => $conversation->id,
            'participant_type' => Workspace::class,
            'participant_id' => $workspace->id,
            'agent' => FinancialAssistantAgent::class,
            'role' => $role,
            'content' => $content,
            'attachments' => [],
            'steps' => [],
            'usage' => [],
            'meta' => [],
            'status' => MessageStatus::Completed,
        ]);
    }
}
