import { Check, CheckCheck, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { router, usePage } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { useState } from 'react';
import { toast } from 'sonner';

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

interface NotificationDrawerProps {
    notifications: NotificationData[];
    isLoading: boolean;
    unreadCount: number;
    onRefresh: () => Promise<void>;
    onClose: () => void;
}

export function NotificationDrawer({
    notifications,
    isLoading,
    unreadCount,
    onRefresh,
    onClose,
}: NotificationDrawerProps) {
    const { props } = usePage();
    const workspaceUuid = props.workspace?.uuid;
    const [markingAll, setMarkingAll] = useState(false);

    const handleMarkAsRead = async (notificationId: string) => {
        try {
            const response = await fetch(route('notifications.read', {
                workspace: workspaceUuid,
                notification: notificationId,
            }), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                toast.success('Notificação marcada como lida.');
                await onRefresh();
            }
        } catch {
            toast.error('Erro ao marcar notificação como lida.');
        }
    };

    const handleMarkAllAsRead = async () => {
        setMarkingAll(true);
        try {
            const response = await fetch(route('notifications.mark-all-as-read', {
                workspace: workspaceUuid,
            }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                toast.success('Todas as notificações foram marcadas como lidas.');
                await onRefresh();
            }
        } catch {
            toast.error('Erro ao marcar notificações como lidas.');
        } finally {
            setMarkingAll(false);
        }
    };

    const handleActionClick = (actionUrl: string) => {
        router.visit(actionUrl);
        onClose();
    };

    const formatRelativeTime = (dateString: string) => {
        try {
            return formatDistanceToNow(new Date(dateString), {
                addSuffix: true,
                locale: ptBR,
            });
        } catch {
            return dateString;
        }
    };

    return (
        <div className="flex flex-col flex-1 overflow-hidden">
            {/* Header actions */}
            {unreadCount > 0 && (
                <div className="px-4 py-2 flex items-center justify-between border-b">
                    <span className="text-sm text-muted-foreground">
                        {unreadCount} não {unreadCount === 1 ? 'lida' : 'lidas'}
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleMarkAllAsRead}
                        disabled={markingAll}
                        className="h-7 text-xs"
                    >
                        <CheckCheck className="mr-1 h-3 w-3" />
                        Marcar todas como lidas
                    </Button>
                </div>
            )}

            {/* Content */}
            <div className="flex-1 overflow-y-auto">
                {isLoading ? (
                    <div className="p-4 space-y-3">
                        {[1, 2, 3].map((i) => (
                            <div key={i} className="space-y-2">
                                <div className="h-4 w-3/4 bg-muted rounded animate-pulse" />
                                <div className="h-3 w-1/2 bg-muted rounded animate-pulse" />
                                <div className="h-3 w-1/4 bg-muted rounded animate-pulse" />
                            </div>
                        ))}
                    </div>
                ) : notifications.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-full p-6 text-center">
                        <p className="text-sm text-muted-foreground">
                            Nenhuma notificação no momento.
                        </p>
                    </div>
                ) : (
                    <div className="divide-y">
                        {notifications.map((notification) => (
                            <div
                                key={notification.id}
                                className={`p-4 space-y-2 ${!notification.is_read ? 'bg-muted/30' : ''}`}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex-1 space-y-1">
                                        <p className={`text-sm font-medium ${!notification.is_read ? 'text-foreground' : 'text-muted-foreground'}`}>
                                            {!notification.is_read && (
                                                <span className="inline-block h-2 w-2 rounded-full bg-primary mr-2" />
                                            )}
                                            {notification.title || 'Notificação'}
                                        </p>
                                        {notification.message && (
                                            <p className="text-sm text-muted-foreground">
                                                {notification.message}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            {formatRelativeTime(notification.created_at)}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2 pt-1">
                                    {notification.action_url && notification.action_text && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="h-7 text-xs"
                                            onClick={() => handleActionClick(notification.action_url!)}
                                        >
                                            <ExternalLink className="mr-1 h-3 w-3" />
                                            {notification.action_text}
                                        </Button>
                                    )}
                                    {!notification.is_read && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 text-xs"
                                            onClick={() => handleMarkAsRead(notification.id)}
                                        >
                                            <Check className="mr-1 h-3 w-3" />
                                            Marcar como lida
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
