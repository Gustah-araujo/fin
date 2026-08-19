import { usePage } from '@inertiajs/react';

export function useWorkspace() {
    const { workspace } = usePage().props;

    if (!workspace) {
        throw new Error(
            'Shared "workspace" prop is missing — page is not workspace-scoped.',
        );
    }

    return workspace;
}
