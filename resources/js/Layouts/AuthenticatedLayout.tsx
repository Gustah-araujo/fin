import { TooltipProvider } from '@/components/ui/tooltip';
import AppSidebar from '@/Components/AppSidebar';
import AppHeader from '@/Components/AppHeader';
import { useState, useCallback, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { Toaster, toast } from 'sonner';

interface Props {
    children: React.ReactNode;
}

const COLLAPSED_WIDTH = '68px';
const EXPANDED_WIDTH = '240px';

export default function AuthenticatedLayout({ children }: Props) {
    const { flash } = usePage().props;
    const [collapsed, setCollapsed] = useState(false);
    const [isMounted, setIsMounted] = useState(false);
    const [isMobile, setIsMobile] = useState(false);
    const toggleCollapsed = useCallback(
        () => setCollapsed((prev) => !prev),
        [],
    );

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
            window.dispatchEvent(
                new CustomEvent('toast', {
                    detail: { type: 'success', message: flash.success },
                }),
            );
        }
        if (flash?.error) {
            toast.error(flash.error);
            window.dispatchEvent(
                new CustomEvent('toast', {
                    detail: { type: 'error', message: flash.error },
                }),
            );
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        setIsMounted(true);
    }, []);

    useEffect(() => {
        const mql = window.matchMedia('(min-width: 1024px)');
        setIsMobile(!mql.matches);
        const handler = (e: MediaQueryListEvent) => setIsMobile(!e.matches);
        mql.addEventListener('change', handler);
        return () => mql.removeEventListener('change', handler);
    }, []);

    const sidebar = <AppSidebar collapsed={false} onToggle={toggleCollapsed} />;
    const marginLeft = isMobile
        ? '0px'
        : !isMounted
          ? EXPANDED_WIDTH
          : collapsed
            ? COLLAPSED_WIDTH
            : EXPANDED_WIDTH;

    return (
        <TooltipProvider delayDuration={200}>
            <div className="flex min-h-screen bg-background">
                <div className="hidden lg:block">
                    <AppSidebar
                        collapsed={collapsed}
                        onToggle={toggleCollapsed}
                    />
                </div>

                <div
                    className="flex flex-1 flex-col transition-all duration-300"
                    style={{
                        marginLeft: `calc(env(safe-area-inset-left) + ${marginLeft})`,
                    }}
                >
                    <AppHeader
                        collapsed={collapsed}
                        onToggle={toggleCollapsed}
                        sidebar={sidebar}
                    />
                    <main className="flex-1 p-6">{children}</main>
                </div>
            </div>
            <Toaster position="top-right" closeButton />
        </TooltipProvider>
    );
}
