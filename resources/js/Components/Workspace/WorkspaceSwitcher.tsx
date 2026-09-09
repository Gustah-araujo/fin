import { useState } from 'react';
import { usePage, useForm, router } from '@inertiajs/react';
import { ChevronsUpDown, Settings, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import RoleBadge from '@/Components/RoleBadge';
import { cn } from '@/lib/utils';

type WorkspaceRole = 'admin' | 'editor' | 'viewer';

export default function WorkspaceSwitcher() {
    const { workspaces, workspace } = usePage().props;
    const [open, setOpen] = useState(false);

    const switchForm = useForm({ workspace_uuid: '' });

    const handleSwitch = (uuid: string) => {
        switchForm.setData('workspace_uuid', uuid);
        switchForm.post(route('workspace.activate'), {
            preserveScroll: true,
            onSuccess: () => router.reload(),
        });
    };

    if (!workspace) {
        return null;
    }

    return (
        <div className="w-full pb-2">
            <DropdownMenu open={open} onOpenChange={setOpen}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        className="w-full justify-between px-2 h-9 font-normal"
                    >
                        <span className="truncate text-sm font-medium">
                            {workspace.name}
                        </span>
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-56" align="start">
                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                        Workspaces
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {workspaces?.map((w) => (
                        <DropdownMenuItem
                            key={w.uuid}
                            onClick={() => handleSwitch(w.uuid)}
                            className={cn(
                                'cursor-pointer',
                                w.uuid === workspace.uuid && 'bg-accent',
                            )}
                        >
                            <span className="truncate flex-1">{w.name}</span>
                            {w.role && (
                                <RoleBadge role={w.role as WorkspaceRole} />
                            )}
                        </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onClick={() => router.visit(route('workspace.create'))}
                        className="cursor-pointer"
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Novo workspace
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onClick={() =>
                            router.visit(
                                route('workspace.settings', {
                                    workspace: workspace.uuid,
                                }),
                            )
                        }
                        className="cursor-pointer"
                    >
                        <Settings className="mr-2 h-4 w-4" />
                        Configurações
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
