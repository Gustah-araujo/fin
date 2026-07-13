import { TooltipProvider } from '@/components/ui/tooltip';
import AppSidebar from '@/Components/AppSidebar';
import AppHeader from '@/Components/AppHeader';
import { useState, useCallback, useEffect } from 'react';

interface Props {
    children: React.ReactNode;
}

const COLLAPSED_WIDTH = '68px';
const EXPANDED_WIDTH = '240px';

export default function AuthenticatedLayout({ children }: Props) {
    const [collapsed, setCollapsed] = useState(false);
    const [isMounted, setIsMounted] = useState(false);
    const toggleCollapsed = useCallback(() => setCollapsed((prev) => !prev), []);

    useEffect(() => {
        setIsMounted(true);
    }, []);

    const sidebar = <AppSidebar collapsed={false} onToggle={toggleCollapsed} />;
    const marginLeft = !isMounted ? EXPANDED_WIDTH : collapsed ? COLLAPSED_WIDTH : EXPANDED_WIDTH;

    return (
        <TooltipProvider delayDuration={200}>
            <div className="flex min-h-screen bg-background">
                <div className="hidden lg:block">
                    <AppSidebar collapsed={collapsed} onToggle={toggleCollapsed} />
                </div>

                <div
                    className="flex flex-1 flex-col transition-all duration-300"
                    style={{ marginLeft: `calc(env(safe-area-inset-left) + ${marginLeft})` }}
                >
                    <AppHeader collapsed={collapsed} onToggle={toggleCollapsed} sidebar={sidebar} />
                    <main className="flex-1 p-6">
                        {children}
                    </main>
                </div>
            </div>
        </TooltipProvider>
    );
}
