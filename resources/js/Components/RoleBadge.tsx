import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface RoleBadgeProps {
    role: 'admin' | 'editor' | 'viewer';
    className?: string;
}

const roleConfig: Record<string, { label: string; className: string }> = {
    admin: { label: 'Administrador', className: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400' },
    editor: { label: 'Editor', className: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    viewer: { label: 'Visualizador', className: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400' },
};

export default function RoleBadge({ role, className }: RoleBadgeProps) {
    const config = roleConfig[role] ?? roleConfig.viewer;

    return (
        <Badge variant="secondary" className={cn('font-normal', config.className, className)}>
            {config.label}
        </Badge>
    );
}
