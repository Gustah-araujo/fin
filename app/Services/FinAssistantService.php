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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Files\File;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

class FinAssistantService
{
    /**
     * Prompt used when the user message has attachments but no text content.
     */
    private const EMPTY_CONTENT_FALLBACK_PROMPT = 'Analise o(s) arquivo(s) em anexo.';

    public function __construct(private readonly FinAttachmentService $attachments) {}

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
     * Create a conversation and persist its first message in a single transaction.
     *
     * @param  array<int, UploadedFile>  $attachments
     * @return array{Conversation, FinAssistantRun}
     */
    public function startConversation(Workspace $workspace, User $user, string $title, ?string $content, array $attachments = []): array
    {
        return DB::transaction(function () use ($workspace, $user, $title, $content, $attachments): array {
            $conversation = $this->createConversation($workspace, $user, $title);
            $run = $this->sendMessage($workspace, $user, $conversation, $content, $attachments);

            return [$conversation, $run];
        });
    }

    /**
     * Persist the user message, create a pending run, and dispatch the required jobs.
     *
     * @param  array<int, UploadedFile>  $attachments
     */
    public function sendMessage(Workspace $workspace, User $user, Conversation $conversation, ?string $content = null, array $attachments = []): FinAssistantRun
    {
        $storedAttachments = [];

        foreach ($attachments as $file) {
            $storedAttachments[] = $this->attachments->serialize(
                $this->attachments->store($workspace, $file),
            );
        }

        $isFirstMessage = $conversation->messages()->where('role', 'user')->count() === 0;

        $this->persistUserMessage($conversation, $workspace, $content ?? '', $storedAttachments);

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

            $attachmentFiles = $this->hydrateAttachments($conversation);

            if ($attachmentFiles !== [] && trim($promptContent) === '') {
                $promptContent = self::EMPTY_CONTENT_FALLBACK_PROMPT;
            }

            $user = User::findOrFail($run->user_id);
            $agent = new FinancialAssistantAgent($workspace, $conversation->id, $user);

            $response = $attachmentFiles === []
                ? $agent->withMessages($historyMessages)->prompt($promptContent)
                : $agent->withMessages($historyMessages)->prompt($promptContent, attachments: $attachmentFiles);

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
     *
     * @param  array<int, array{type: string, name: string|null, path: string, disk: string}>  $attachments
     */
    public function persistUserMessage(Conversation $conversation, Workspace $workspace, string $content, array $attachments = []): ConversationMessage
    {
        return $this->persistMessage($conversation, $workspace, 'user', $content, $attachments);
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
     *
     * @param  array<int, array{type: string, name: string|null, path: string, disk: string}>  $attachments
     */
    private function persistMessage(Conversation $conversation, Workspace $workspace, string $role, string $content, array $attachments = []): ConversationMessage
    {
        return ConversationMessage::create([
            'id' => Str::orderedUuid()->toString(),
            'conversation_id' => $conversation->id,
            'participant_type' => Workspace::class,
            'participant_id' => $workspace->id,
            'agent' => FinancialAssistantAgent::class,
            'role' => $role,
            'content' => $content,
            'attachments' => $attachments,
            'steps' => [],
            'usage' => [],
            'meta' => [],
            'status' => MessageStatus::Completed,
        ]);
    }

    /**
     * Rebuild the SDK files attached to the last user message of the conversation.
     *
     * @return list<File>
     */
    private function hydrateAttachments(Conversation $conversation): array
    {
        $lastUserMessage = $conversation->messages()
            ->where('role', MessageRole::User->value)
            ->orderByDesc('id')
            ->first();

        $files = [];

        foreach ($lastUserMessage?->attachments ?? [] as $metadata) {
            if (($file = $this->attachments->hydrate((array) $metadata)) !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
