import { useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { FormEvent } from 'react';

interface ForgotPasswordProps {
    status?: string;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/forgot-password');
    }

    return (
        <GuestLayout status={status}>
            <div className="mb-6">
                <h1 className="text-xl font-semibold tracking-tight">Esqueci minha senha</h1>
                <p className="text-sm text-muted-foreground mt-1">
                    Informe seu email para receber o link de recuperação
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="seu@email.com"
                    />
                    {errors.email && (
                        <p className="text-sm text-destructive">{errors.email}</p>
                    )}
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    Enviar link de recuperação
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    <a href={route('login')} className="text-primary hover:underline">
                        Voltar para o login
                    </a>
                </p>
            </form>
        </GuestLayout>
    );
}
