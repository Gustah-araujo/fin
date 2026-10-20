import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { NotificationDrawer } from './NotificationDrawer';
import { useNotificationPolling } from '@/hooks/use-notification-polling';

export default function NotificationBell() {
    const { props } = usePage();
    const workspaceUuid = props.workspace?.uuid;
    const [open, setOpen] = useState(false);
    const { unreadCount, notifications, isLoading, refresh } = useNotificationPolling(workspaceUuid);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center rounded-full p-0 text-[10px]"
                        >
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </Badge>
                    )}
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full sm:max-w-sm p-0 flex flex-col">
                <SheetHeader className="px-4 pt-4 pb-2 border-b">
                    <SheetTitle>Notificações</SheetTitle>
                </SheetHeader>
                <NotificationDrawer
                    notifications={notifications}
                    isLoading={isLoading}
                    unreadCount={unreadCount}
                    onRefresh={refresh}
                    onClose={() => setOpen(false)}
                />
            </SheetContent>
        </Sheet>
    );
}
