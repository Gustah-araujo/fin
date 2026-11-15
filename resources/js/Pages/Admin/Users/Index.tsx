import { Link, useForm } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { DataTable } from '@/Components/DataTable/DataTable';
import DropdownButton from '@/Components/DropdownButton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { AdminUser } from '@/types/admin';
import type { DataTableColumn } from '@/types/datatable';

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pt-BR');
}

export default function Index() {
    const [reloadTrigger, setReloadTrigger] = useState(0);

    const bumpReload = useCallback(() => {
        setReloadTrigger((n) => n + 1);
    }, []);

    const columns: DataTableColumn<AdminUser>[] = [
        {
            key: 'name',
            header: 'Nome',
            sortable: true,
        },
        {
            key: 'email',
            header: 'E-mail',
            sortable: true,
        },
        {
            key: 'email_verified_at',
            header: 'Status',
            filter: {
                type: 'select',
                options: [
                    { label: 'Verificados', value: 'verified' },
                    { label: 'Não Verificados', value: 'unverified' },
                ],
            },
            cell: (row) =>
                row.email_verified_at === null ? (
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
                ),
        },
        {
            key: 'workspaces_count',
            header: 'Workspaces',
            cell: (row) => row.workspaces_count ?? 0,
        },
        {
            key: 'created_at',
            header: 'Data de Cadastro',
            sortable: true,
            cell: (row) => formatDate(row.created_at),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (row) => <UserActions user={row} onMutated={bumpReload} />,
        },
    ];

    return (
        <AdminLayout>
            <div className="space-y-6">
                <h1 className="text-3xl font-bold tracking-tight">Usuários</h1>

                <DataTable
                    endpoint={route('admin.users.datatable')}
                    columns={columns}
                    reloadTrigger={reloadTrigger}
                    emptyState={
                        <div className="flex flex-col items-center justify-center py-12">
                            <p className="text-sm text-muted-foreground">
                                Nenhum usuário encontrado.
                            </p>
                        </div>
                    }
                />
            </div>
        </AdminLayout>
    );
}

function UserActions({
    user,
    onMutated,
}: {
    user: AdminUser;
    onMutated: () => void;
}) {
    const { post } = useForm({});

    const isUnverified = user.email_verified_at === null;

    function handleResend() {
        post(route('admin.users.resend-verification', { user: user.uuid }), {
            preserveScroll: true,
            onSuccess: onMutated,
        });
    }

    function handleVerify() {
        post(route('admin.users.verify', { user: user.uuid }), {
            preserveScroll: true,
            onSuccess: onMutated,
        });
    }

    return (
        <div className="flex items-center justify-end gap-2">
            <Button variant="outline" size="sm" asChild>
                <Link
                    href={route('admin.users.show', { user: user.uuid })}
                    data-testid="admin-user-view"
                >
                    Visualizar
                </Link>
            </Button>
            {isUnverified && (
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
            )}
        </div>
    );
}
