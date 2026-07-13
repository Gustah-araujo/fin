import { useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';

interface VerifyEmailProps {
    status?: string;
}

export default function VerifyEmail({ status }: VerifyEmailProps) {
    const { post, processing } = useForm({});

    return (
        <GuestLayout status={status}>
            <div className="mb-6">
                <h1 className="text-xl font-semibold tracking-tight">Verifique seu email</h1>
                <p className="text-sm text-muted-foreground mt-1">
                    Um link de verificação foi enviado para seu email. Verifique sua caixa de entrada e clique no link para confirmar.
                </p>
            </div>

            <div className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    Não recebeu o email? Verifique a pasta de spam ou solicite um novo link.
                </p>

                <Button
                    className="w-full"
                    variant="outline"
                    disabled={processing}
                    onClick={() => post('/email/verification-notification')}
                >
                    Reenviar email de verificação
                </Button>

                <div className="text-center">
                    <button
                        type="button"
                        className="text-sm text-primary hover:underline"
                        onClick={() => post('/logout')}
                    >
                        Sair
                    </button>
                </div>
            </div>
        </GuestLayout>
    );
}
