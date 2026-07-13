import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Create() {
    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <h1 className="text-2xl font-semibold tracking-tight">Nova Conta</h1>
            </div>
        </AuthenticatedLayout>
    );
}
