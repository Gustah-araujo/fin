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
    accounts: Account[];
}

export default function Index({ accounts }: Props) {
    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <h1 className="text-2xl font-semibold tracking-tight">Contas</h1>
                <p>{accounts.length} conta(s)</p>
            </div>
        </AuthenticatedLayout>
    );
}
