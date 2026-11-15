import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    Building2,
    LayoutDashboard,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { Toaster } from 'sonner';
import { useFlashToasts } from '@/hooks/use-flash-toasts';

interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    testId: string;
    exact?: boolean;
}

interface Props {
    children: React.ReactNode;
}

export default function AdminLayout({ children }: Props) {
    useFlashToasts();
    const { url } = usePage();

    const navigation: NavItem[] = [
        {
            label: 'Dashboard',
            href: route('admin.dashboard'),
            icon: LayoutDashboard,
            testId: 'admin-sidebar-dashboard',
            exact: true,
        },
        {
            label: 'Usuários',
            href: route('admin.users.index'),
            icon: Users,
            testId: 'admin-sidebar-users',
        },
        {
            label: 'Workspaces',
            href: route('admin.workspaces.index'),
            icon: Building2,
            testId: 'admin-sidebar-workspaces',
        },
    ];

    function isActive(item: NavItem): boolean {
        if (item.exact) {
            return url === item.href || url === `${item.href}/`;
        }
        return url === item.href || url.startsWith(`${item.href}/`);
    }

    return (
        <div className="flex min-h-screen bg-background">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-[240px] flex-col bg-sidebar text-sidebar-foreground border-r border-sidebar-border lg:flex">
                <div className="flex items-center h-14 px-4 gap-3 border-b border-sidebar-border">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground font-bold text-sm">
                        F
                    </div>
                    <span className="font-semibold tracking-tight">
                        Fin · Backoffice Admin
                    </span>
                </div>

                <nav className="flex-1 overflow-y-auto py-3 px-2">
                    <div className="space-y-0.5">
                        {navigation.map((item) => {
                            const active = isActive(item);
                            return (
                                <Link
                                    key={item.testId}
                                    href={item.href}
                                    data-testid={item.testId}
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                        active
                                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                            : 'text-sidebar-foreground/60 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground',
                                    )}
                                >
                                    <item.icon className="h-4 w-4 shrink-0" />
                                    <span>{item.label}</span>
                                </Link>
                            );
                        })}
                    </div>
                </nav>

                <div className="p-3 border-t border-sidebar-border">
                    <Link
                        href={route('workspace.select')}
                        className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-sidebar-foreground/60 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground transition-colors"
                    >
                        <span>Voltar para o App</span>
                    </Link>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col lg:ml-[240px]">
                <header className="sticky top-0 z-30 flex h-14 items-center border-b bg-background px-4 lg:px-6">
                    <h1 className="text-sm font-semibold tracking-tight">
                        Fin · Backoffice Admin
                    </h1>
                </header>
                <main className="flex-1 p-6">{children}</main>
            </div>

            <Toaster position="top-right" closeButton />
        </div>
    );
}
