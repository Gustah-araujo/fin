import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    LayoutDashboard,
    Building2,
    ArrowLeftRight,
    CreditCard,
    TrendingUp,
    CalendarClock,
    MessageSquare,
    Upload,
    ChevronLeft,
    ChevronRight,
    type LucideIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
}

interface NavSection {
    title: string;
    items: NavItem[];
}

interface Props {
    collapsed: boolean;
    onToggle: () => void;
}

export default function AppSidebar({ collapsed, onToggle }: Props) {
    const { url } = usePage();
    const { props } = usePage<{ workspace?: { uuid: string; name: string } }>();
    const workspaceUuid = props.workspace?.uuid;

    const navigation: NavSection[] = [
        {
            title: 'Principal',
            items: [
                { label: 'Dashboard', href: '/', icon: LayoutDashboard },
                ...(workspaceUuid
                    ? [
                          {
                              label: 'Contas',
                              href: route('accounts.index', { workspace: workspaceUuid }),
                              icon: Building2,
                          },
                      ]
                    : [{ label: 'Contas', href: '/accounts', icon: Building2 }]),
                { label: 'Despesas', href: '/expenses', icon: ArrowLeftRight },
                { label: 'Receitas', href: '/incomes', icon: TrendingUp },
            ],
        },
        {
            title: 'Cartões',
            items: [
                { label: 'Cartões de Crédito', href: '/credit-cards', icon: CreditCard },
            ],
        },
        {
            title: 'Planejamento',
            items: [
                { label: 'Despesas Futuras', href: '/future-expenses', icon: CalendarClock },
            ],
        },
        {
            title: 'IA',
            items: [
                { label: 'Chat', href: '/chat', icon: MessageSquare },
                { label: 'Importar', href: '/import', icon: Upload },
            ],
        },
    ];

    function isActive(href: string) {
        if (href === '/') return url === '/';
        return url.startsWith(href);
    }

    return (
        <aside
            className={cn(
                'fixed inset-y-0 left-0 z-40 flex flex-col bg-sidebar text-sidebar-foreground border-r border-sidebar-border transition-all duration-300',
                collapsed ? 'w-[68px]' : 'w-[240px]'
            )}
        >
            <div className={cn('flex items-center h-14 px-4 border-b border-sidebar-border', collapsed ? 'justify-center' : 'gap-3')}>
                {!collapsed && (
                    <>
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground font-bold text-sm">
                            F
                        </div>
                        <span className="font-semibold tracking-tight">Fin</span>
                    </>
                )}
                {collapsed && (
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground font-bold text-sm">
                        F
                    </div>
                )}
            </div>

            <nav className="flex-1 overflow-y-auto py-3 px-2">
                {navigation.map((section) => (
                    <div key={section.title} className="mb-4">
                        {!collapsed && (
                            <h3 className="mb-1 px-3 text-[10px] font-semibold uppercase tracking-wider text-sidebar-foreground/40">
                                {section.title}
                            </h3>
                        )}
                        <div className="space-y-0.5">
                            {section.items.map((item) => {
                                const active = isActive(item.href);
                                const link = (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={cn(
                                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                            active
                                                ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                                : 'text-sidebar-foreground/60 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground',
                                            collapsed && 'justify-center px-2'
                                        )}
                                    >
                                        <item.icon className="h-4 w-4 shrink-0" />
                                        {!collapsed && <span>{item.label}</span>}
                                    </Link>
                                );

                                if (collapsed) {
                                    return (
                                        <Tooltip key={item.href}>
                                            <TooltipTrigger asChild>
                                                {link}
                                            </TooltipTrigger>
                                            <TooltipContent side="right" className="flex items-center gap-2">
                                                {item.label}
                                            </TooltipContent>
                                        </Tooltip>
                                    );
                                }

                                return link;
                            })}
                        </div>
                    </div>
                ))}
            </nav>

            <Separator className="bg-sidebar-border" />

            <div className="p-3">
                {!collapsed && (
                    <div className="rounded-lg bg-sidebar-accent/50 px-3 py-2">
                        <p className="text-xs font-medium text-sidebar-foreground/60">Workspace</p>
                        <p className="text-sm font-medium truncate">
                            {props.workspace?.name ?? 'Workspace Pessoal'}
                        </p>
                    </div>
                )}
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'mt-2 w-full text-sidebar-foreground/50 hover:text-sidebar-foreground hover:bg-sidebar-accent/50',
                        collapsed && 'mt-1'
                    )}
                    onClick={onToggle}
                >
                    {collapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
                </Button>
            </div>
        </aside>
    );
}
