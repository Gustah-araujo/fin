import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import DropdownButton from '@/Components/DropdownButton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AdminUser, AdminWorkspaceRef, UserType } from '@/types/admin';

const userTypeLabels: Record<UserType, string> = {
    admin: 'Administrador',
    customer: 'Cliente',
};

const workspaceRoleLabels: Record<string, string> = {
    admin: 'Administrador',
    editor: 'Editor',
    viewer: 'Visualizador',
};

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pt-BR');
}

interface Props {
    user: AdminUser;
    workspaces: AdminWorkspaceRef[];
}

export default function Show({ user, workspaces }: Props) {
    const { post } = useForm({});

    const isUnverified = user.email_verified_at === null;

    function handleResend() {
        post(route('admin.users.resend-verification', { user: user.uuid }), {
            preserveScroll: true,
            onSuccess: () => router.reload(),
        });
    }

    function handleVerify() {
        post(route('admin.users.verify', { user: user.uuid }), {
            preserveScroll: true,
            onSuccess: () => router.reload(),
        });
    }

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
                            href={route('admin.users.index')}
                            data-testid="admin-user-back"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-3xl font-bold tracking-tight">
                            {user.name}
                        </h1>
                        {isUnverified ? (
                            <Badge
                                variant="outline"
                                className="border-amber-600/40 bg-amber-600/10 text-amber-600"
                                data-testid="admin-user-status-pending"
                            >
                                Pendente
                            </Badge>
                        ) : (
                            <Badge
                                variant="secondary"
                                data-testid="admin-user-status-verified"
                            >
                                Verificado
                            </Badge>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {user.email}
                    </p>
                </div>

                {isUnverified && (
                    <div className="flex justify-end">
                        <DropdownButton
                            label="Ações"
                            triggerTestId="admin-user-actions-trigger"
                            items={[
                                {
                                    label: 'Reenviar e-mail de confirmação',
                                    testId: 'admin-user-resend-verification',
                                    onSelect: handleResend,
                                },
                                {
                                    label: 'Marcar como verificado manualmente',
                                    testId: 'admin-user-manual-verify',
                                    onSelect: handleVerify,
                                },
                            ]}
                        />
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Tipo
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold"
                                data-testid="admin-user-detail-type"
                            >
                                {userTypeLabels[user.type]}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Verificação de E-mail
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold"
                                data-testid="admin-user-detail-status"
                            >
                                {isUnverified ? 'Pendente' : 'Verificado'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Data de Cadastro
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold"
                                data-testid="admin-user-detail-created-at"
                            >
                                {formatDate(user.created_at)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg font-semibold">
                            Workspaces do Usuário
                        </CardTitle>
                        <CardDescription>
                            {workspaces.length === 1
                                ? '1 workspace'
                                : `${workspaces.length} workspaces`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {workspaces.length === 0 ? (
                            <p
                                className="text-sm text-muted-foreground"
                                data-testid="admin-user-workspaces-empty"
                            >
                                Este usuário não participa de nenhum workspace.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {workspaces.map((workspace) => (
                                    <li key={workspace.uuid}>
                                        <Link
                                            href={route(
                                                'admin.workspaces.show',
                                                {
                                                    workspace: workspace.uuid,
                                                },
                                            )}
                                            className="flex items-center justify-between gap-4 py-3 transition-colors first:pt-0 last:pb-0 hover:bg-accent/50"
                                            data-testid={`admin-user-workspace-${workspace.uuid}`}
                                        >
                                            <span className="flex items-center gap-2 text-sm font-medium">
                                                <Building2 className="h-4 w-4 text-muted-foreground" />
                                                {workspace.name}
                                            </span>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    workspace.role === 'admin'
                                                        ? 'border-primary/40 bg-primary/10 text-primary'
                                                        : undefined
                                                }
                                                data-testid={`admin-user-workspace-role-${workspace.uuid}`}
                                            >
                                                {workspaceRoleLabels[
                                                    workspace.role
                                                ] ?? workspace.role}
                                            </Badge>
                                        </Link>
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
