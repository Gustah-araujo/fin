import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

interface Invite {
    uuid: string;
    email: string;
    role: string;
    status: string;
    inviter: {
        uuid: string;
        name: string;
    };
    workspace: {
        uuid: string;
        name: string;
    };
}

interface PendingInvitesListProps {
    invites: Invite[];
}

export default function PendingInvitesList({ invites }: PendingInvitesListProps) {
    if (invites.length === 0) {
        return (
            <p className="text-sm text-muted-foreground py-4">Nenhum convite pendente.</p>
        );
    }

    return (
        <div className="space-y-2">
            {invites.map((invite) => (
                <InviteRow key={invite.uuid} invite={invite} />
            ))}
        </div>
    );
}

function InviteRow({ invite }: { invite: Invite }) {
    const { post, processing } = useForm({});

    const roleLabel =
        invite.role === 'admin' ? 'Administrador' : invite.role === 'editor' ? 'Editor' : 'Visualizador';

    return (
        <div className="flex items-center justify-between rounded-lg border p-3">
            <div className="space-y-1">
                <p className="text-sm font-medium">{invite.email}</p>
                <p className="text-xs text-muted-foreground">
                    Convidado como {roleLabel} por {invite.inviter.name} &middot;{' '}
                    {invite.workspace.name}
                </p>
            </div>
            <div className="flex gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    disabled={processing}
                    onClick={() => post(`/invites/${invite.uuid}/accept`)}
                >
                    Aceitar
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    disabled={processing}
                    onClick={() => post(`/invites/${invite.uuid}/decline`)}
                >
                    Recusar
                </Button>
            </div>
        </div>
    );
}
