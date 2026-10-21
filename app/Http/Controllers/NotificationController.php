<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Workspace;
use App\Services\NotificationService;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Workspace $workspace, NotificationService $service): JsonResponse
    {
        $user = request()->user();
        $notifications = $service->getNotifications($user, $workspace);

        return response()->json([
            'unread_count' => $service->getUnreadCount($user, $workspace),
            'notifications' => NotificationResource::collection($notifications),
        ]);
    }

    public function markAsRead(Workspace $workspace, DatabaseNotification $notification, NotificationService $service): JsonResponse
    {
        $user = request()->user();
        $service->markAsRead($user, $notification);

        return response()->json([
            'unread_count' => $service->getUnreadCount($user, $workspace),
            'notification' => new NotificationResource($notification->fresh()),
        ]);
    }

    public function markAllAsRead(Workspace $workspace, NotificationService $service): JsonResponse
    {
        $user = request()->user();
        $service->markAllAsRead($user, $workspace);

        Toast::success('Todas as notificações foram marcadas como lidas.');

        return response()->json([
            'unread_count' => 0,
        ]);
    }
}
