<?php

declare(strict_types=1);

namespace App\Notifications\Workspace;

use App\Models\Workspace;
use Illuminate\Notifications\Notification;

class FinancialInsightNotification extends Notification
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly int $insightCount,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification (database channel).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'workspace_uuid' => $this->workspace->uuid,
            'title' => 'Análise financeira concluída',
            'message' => "{$this->insightCount} novos insights financeiros identificados para {$this->workspace->name}.",
            'action_url' => null,
            'action_text' => null,
        ];
    }
}
