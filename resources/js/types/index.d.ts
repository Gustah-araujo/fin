declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: {
                uuid: string;
                name: string;
                email: string;
                avatar?: string;
            } | null;
        };
        workspaces: Array<{
            uuid: string;
            name: string;
            description?: string;
            role?: string;
        }>;
        workspace: {
            uuid: string;
            name: string;
            role?: string;
        } | null;
        status: string | null;
        flash?: {
            success?: string;
            error?: string;
        };
    }
}

export {};

export interface NotificationData {
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

export interface NotificationsIndexResponse {
    unread_count: number;
    notifications: NotificationData[];
}

export interface NotificationMarkReadResponse {
    unread_count: number;
    notification: NotificationData;
}

export interface NotificationMarkAllReadResponse {
    unread_count: number;
}
