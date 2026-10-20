<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotificationService
{
    public function getUnreadCount(User $user, Workspace $workspace): int
    {
        return DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->where('data->workspace_uuid', $workspace->uuid)
            ->count();
    }

    public function getNotifications(User $user, Workspace $workspace, int $limit = 20): Collection
    {
        return DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('data->workspace_uuid', $workspace->uuid)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function markAsRead(User $user, DatabaseNotification $notification): void
    {
        if ($notification->notifiable_id !== $user->id || $notification->notifiable_type !== User::class) {
            throw new HttpException(403, 'Esta notificação não pertence a você.');
        }

        $notification->markAsRead();
    }

    public function markAllAsRead(User $user, Workspace $workspace): void
    {
        DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('data->workspace_uuid', $workspace->uuid)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
