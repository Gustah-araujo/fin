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
