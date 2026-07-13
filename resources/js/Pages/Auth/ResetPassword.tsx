import { useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { FormEvent } from 'react';

interface ResetPasswordProps {
    email: string;
    token: string;
}

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: email,
        token: token,
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/reset-password');
    }

    return (
        <GuestLayout>
            <div className="mb-6">
                <h1 className="text-xl font-semibold tracking-tight">Redefinir senha</h1>
                <p className="text-sm text-muted-foreground mt-1">
                    Escolha uma nova senha para sua conta
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="password">Nova senha</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="Mínimo 8 caracteres"
                    />
                    {errors.password && (
                        <p className="text-sm text-destructive">{errors.password}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password_confirmation">Confirmar senha</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </div>

                {errors.email && (
                    <p className="text-sm text-destructive">{errors.email}</p>
                )}

                <Button type="submit" className="w-full" disabled={processing}>
                    Redefinir senha
                </Button>
            </form>
        </GuestLayout>
    );
}
