import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { FormEvent } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/workspace');
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-background p-4">
            <div className="w-full max-w-md space-y-6">
                <div className="text-center">
                    <h1 className="text-2xl font-semibold tracking-tight">Criar seu workspace</h1>
                    <p className="text-sm text-muted-foreground mt-2">
                        Um workspace é onde você gerencia suas finanças. Pode ser pessoal ou compartilhado.
                    </p>
                </div>

                <div className="rounded-lg border bg-card p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Nome do workspace</Label>
                            <Input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Ex: Finanças da Casa"
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">{errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Descrição (opcional)</Label>
                            <Input
                                id="description"
                                type="text"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Ex: Controle financeiro da família"
                            />
                        </div>

                        <Button type="submit" className="w-full" disabled={processing}>
                            Criar workspace
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    );
}
