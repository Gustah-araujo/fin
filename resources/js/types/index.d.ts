declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: {
                uuid: string;
                name: string;
                email: string;
                avatar: string | null;
            } | null;
        };
        workspaces: Array<{
            uuid: string;
            name: string;
            description: string | null;
        }>;
        workspace: {
            uuid: string;
            name: string;
            role: string | null;
        } | null;
        status: string | null;
    }
}
