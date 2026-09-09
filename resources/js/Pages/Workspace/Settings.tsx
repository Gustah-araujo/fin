import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import MemberRow from '@/Components/MemberRow';
import PendingInvitesList from '@/Components/PendingInvitesList';
import InviteDialog from '@/Components/InviteDialog';

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
    inviter: {
        uuid: string;
        name: string;
    };
    workspace: {
        uuid: string;
        name: string;
    };
}

interface SettingsPageProps {
    members: Member[];
    invites: Invite[];
    isAdmin: boolean;
}

export default function Settings({
    members,
    invites,
    isAdmin,
}: SettingsPageProps) {
    const { props } = usePage();
    const workspace = props.workspace;
    const currentUserUuid = props.auth.user?.uuid;

    if (!workspace) {
        return null;
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => window.history.back()}
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Configurações do Workspace
                            </h1>
                            <p className="text-sm text-muted-foreground mt-1">
                                {workspace.name}
                            </p>
                        </div>
                    </div>
                    {isAdmin && <InviteDialog workspaceUuid={workspace.uuid} />}
                </div>

                {/* Pending Invites */}
                {invites.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-lg font-medium">
                            Convites pendentes ({invites.length})
                        </h2>
                        <PendingInvitesList invites={invites} />
                    </div>
                )}

                {/* Members */}
                <div className="space-y-3">
                    <h2 className="text-lg font-medium">
                        Membros ({members.length})
                    </h2>
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
            </div>
        </AuthenticatedLayout>
    );
}
