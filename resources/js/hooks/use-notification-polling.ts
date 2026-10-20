import { useCallback, useEffect, useRef, useState } from 'react';

interface NotificationData {
    id: string;
    type: string;
    title: string | null;
    message: string | null;
    action_url: string | null;
    action_text: string | null;
    is_read: boolean;
    created_at: string;
    read_at: string | null;
}

interface PollingResult {
    unreadCount: number;
    notifications: NotificationData[];
    isLoading: boolean;
    refresh: () => Promise<void>;
}

const POLL_INTERVAL_MS = 30_000;

export function useNotificationPolling(
    workspaceUuid: string | undefined,
): PollingResult {
    const [unreadCount, setUnreadCount] = useState(0);
    const [notifications, setNotifications] = useState<NotificationData[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const fetchNotifications = useCallback(async () => {
        if (!workspaceUuid) return;

        try {
            const response = await fetch(
                route('notifications.index', { workspace: workspaceUuid }),
            );

            if (!response.ok) return;

            const data = await response.json();
            setUnreadCount(data.unread_count);
            setNotifications(data.notifications);
        } catch {
            // Silently fail — polling will retry on next interval
        } finally {
            setIsLoading(false);
        }
    }, [workspaceUuid]);

    useEffect(() => {
        if (!workspaceUuid) return;

        setIsLoading(true);
        fetchNotifications();

        intervalRef.current = setInterval(fetchNotifications, POLL_INTERVAL_MS);

        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        };
    }, [workspaceUuid, fetchNotifications]);

    return {
        unreadCount,
        notifications,
        isLoading,
        refresh: fetchNotifications,
    };
}
