import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Account {
    uuid: string;
    name: string;
    type: string;
    initial_balance: number;
    current_balance: number;
    created_at: string;
}

interface Props {
    account: Account;
}

export default function Edit({ account }: Props) {
    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <h1 className="text-2xl font-semibold tracking-tight">Editar {account.name}</h1>
            </div>
        </AuthenticatedLayout>
    );
}
