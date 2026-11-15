import { TooltipProvider } from '@/components/ui/tooltip';
import AppSidebar from '@/Components/AppSidebar';
import AppHeader from '@/Components/AppHeader';
import { useFlashToasts } from '@/hooks/use-flash-toasts';
import { useState, useCallback, useEffect } from 'react';
import { Toaster } from 'sonner';

interface Props {
    children: React.ReactNode;
}

const COLLAPSED_WIDTH = '68px';
const EXPANDED_WIDTH = '240px';

export default function AuthenticatedLayout({ children }: Props) {
    useFlashToasts();
    const [collapsed, setCollapsed] = useState(false);
    const [isMounted, setIsMounted] = useState(false);
    const [isMobile, setIsMobile] = useState(false);
    const toggleCollapsed = useCallback(
        () => setCollapsed((prev) => !prev),
        [],
    );

    // Radix UI (and similar libraries) can leave a scroll-lock on <body>
    // after a Dialog closes (pointer-events: none + data-scroll-locked),
    // which blocks all mouse interactions. Permanently observe <body> and
    // clear any scroll-lock the moment it appears.
    useEffect(() => {
        const clear = (): void => {
            // Only clear pointer-events when Radix reports no open dialogs.
            // Radix uses data-scroll-locked as a counter (absent/0 = no dialogs).
            // We must NOT remove data-scroll-locked itself — Radix manages it.
            const scrollLocked =
                document.body.getAttribute('data-scroll-locked');
            if (!scrollLocked || scrollLocked === '0') {
                document.body.style.removeProperty('pointer-events');
            }
        };

        const observer = new MutationObserver(clear);
        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['style', 'data-scroll-locked'],
        });

        // Also clear on initial mount
        clear();

        return () => observer.disconnect();
    }, []);

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
