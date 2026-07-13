import { useForm } from '@inertiajs/react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import RoleBadge from '@/Components/RoleBadge';
import { MoreHorizontal } from 'lucide-react';

interface Member {
    user: {
        uuid: string;
        name: string;
        email: string;
        avatar: string | null;
    };
    role: string;
    joined_at: string;
}

interface MemberRowProps {
    member: Member;
    workspaceUuid: string;
    canManage: boolean;
    isSelf: boolean;
}

export default function MemberRow({ member, workspaceUuid, canManage, isSelf }: MemberRowProps) {
    const { delete: destroy, put, processing } = useForm({});

    const initials = member.user.name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <div className="flex items-center justify-between rounded-lg border p-3">
            <div className="flex items-center gap-3">
                <Avatar className="h-9 w-9">
                    <AvatarFallback className="text-xs">{initials}</AvatarFallback>
                </Avatar>
                <div>
                    <p className="text-sm font-medium">
                        {member.user.name}
                        {isSelf && <span className="text-muted-foreground ml-1">(você)</span>}
                    </p>
                    <p className="text-xs text-muted-foreground">{member.user.email}</p>
                </div>
            </div>
            <div className="flex items-center gap-3">
                <RoleBadge role={member.role as 'admin' | 'editor' | 'viewer'} />
                {canManage && !isSelf && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() =>
                                    put(`/w/${workspaceUuid}/members/${member.user.uuid}/role`, {
                                        data: { role: 'admin' },
                                    })
                                }
                                disabled={processing}
                            >
                                Tornar Administrador
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() =>
                                    put(`/w/${workspaceUuid}/members/${member.user.uuid}/role`, {
                                        data: { role: 'editor' },
                                    })
                                }
                                disabled={processing}
                            >
                                Tornar Editor
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() =>
                                    put(`/w/${workspaceUuid}/members/${member.user.uuid}/role`, {
                                        data: { role: 'viewer' },
                                    })
                                }
                                disabled={processing}
                            >
                                Tornar Visualizador
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                className="text-destructive"
                                onClick={() =>
                                    destroy(`/w/${workspaceUuid}/members/${member.user.uuid}`)
                                }
                                disabled={processing}
                            >
                                Remover do workspace
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </div>
    );
}
