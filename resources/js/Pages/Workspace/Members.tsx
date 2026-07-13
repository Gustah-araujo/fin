import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InviteDialog from '@/Components/InviteDialog';
import MemberRow from '@/Components/MemberRow';
import PendingInvitesList from '@/Components/PendingInvitesList';
import { usePage } from '@inertiajs/react';

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

interface Invite {
    uuid: string;
    email: string;
    role: string;
    status: string;
    inviter: { uuid: string; name: string };
    workspace: { uuid: string; name: string };
}

interface MembersProps {
    members: Member[];
    invites: Invite[];
    workspace: { uuid: string; name: string };
}

export default function Members({ members, invites, workspace }: MembersProps) {
    const { auth } = usePage<{ auth: { user: { uuid: string } } }>().props;
    const currentUserUuid = auth.user?.uuid;
    const isAdmin = members.some(
        (m) => m.user.uuid === currentUserUuid && m.role === 'admin'
    );

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Membros</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {members.length} membro{members.length !== 1 ? 's' : ''} no workspace
                        </p>
                    </div>
                    {isAdmin && <InviteDialog workspaceUuid={workspace.uuid} />}
                </div>

                {invites.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-lg font-medium">Convites pendentes</h2>
                        <PendingInvitesList invites={invites} />
                    </div>
                )}

                <div className="space-y-2">
                    {members.map((member) => (
                        <MemberRow
                            key={member.user.uuid}
                            member={member}
                            workspaceUuid={workspace.uuid}
                            canManage={isAdmin}
                            isSelf={member.user.uuid === currentUserUuid}
                        />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
