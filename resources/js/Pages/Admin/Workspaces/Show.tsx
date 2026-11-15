import { Link } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AdminMember, AdminWorkspace } from '@/types/admin';

const roleLabels: Record<string, string> = {
    admin: 'Administrador',
    editor: 'Editor',
    viewer: 'Visualizador',
};

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pt-BR');
}

interface Props {
    workspace: AdminWorkspace;
    members: AdminMember[];
}

export default function Show({ workspace, members }: Props) {
    return (
        <AdminLayout>
            <div className="space-y-6">
                <div>
                    <Button
                        variant="ghost"
                        size="sm"
                        asChild
                        className="mb-2 -ml-2"
                    >
                        <Link
                            href={route('admin.workspaces.index')}
                            data-testid="admin-workspace-back"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                    <div className="flex flex-wrap items-center gap-3">
                        <Building2 className="h-6 w-6 text-muted-foreground" />
                        <h1 className="text-3xl font-bold tracking-tight">
                            {workspace.name}
                        </h1>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Nome
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold"
                                data-testid="admin-workspace-detail-name"
                            >
                                {workspace.name}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Data de Criação
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold"
                                data-testid="admin-workspace-detail-created-at"
                            >
                                {formatDate(workspace.created_at)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg font-semibold">
                            Usuários do Workspace
                        </CardTitle>
                        <CardDescription>
                            {members.length === 1
                                ? '1 membro'
                                : `${members.length} membros`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {members.length === 0 ? (
                            <p
                                className="text-sm text-muted-foreground"
                                data-testid="admin-workspace-members-empty"
                            >
                                Este workspace não possui membros.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {members.map((member) => (
                                    <li
                                        key={member.user.uuid}
                                        className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                    >
                                        <Link
                                            href={route('admin.users.show', {
                                                user: member.user.uuid,
                                            })}
                                            className="flex items-center gap-2 text-sm font-medium transition-colors hover:bg-accent/50"
                                            data-testid={`admin-member-${member.user.uuid}`}
                                        >
                                            {member.user.name}
                                            <span className="font-normal text-muted-foreground">
                                                {member.user.email}
                                            </span>
                                        </Link>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-muted-foreground">
                                                Entrou em{' '}
                                                {formatDate(member.joined_at)}
                                            </span>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    member.role === 'admin'
                                                        ? 'border-primary/40 bg-primary/10 text-primary'
                                                        : undefined
                                                }
                                                data-testid={`admin-member-role-${member.user.uuid}`}
                                            >
                                                {roleLabels[member.role] ??
                                                    member.role}
                                            </Badge>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
