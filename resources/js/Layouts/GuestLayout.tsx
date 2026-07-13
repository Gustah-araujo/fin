import type { PropsWithChildren } from 'react';

interface GuestLayoutProps {
    status?: string;
}

export default function GuestLayout({ status, children }: PropsWithChildren<GuestLayoutProps>) {
    return (
        <div className="min-h-screen flex items-center justify-center bg-background p-4">
            <div className="w-full max-w-md space-y-6">
                {status && (
                    <div className="rounded-md bg-emerald-50 p-4 text-sm font-medium text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
                        {status}
                    </div>
                )}
                <div className="rounded-lg border bg-card p-6 shadow-sm">
                    {children}
                </div>
            </div>
        </div>
    );
}
