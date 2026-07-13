import { Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ColorPicker } from '@/components/ui/color-picker';

export default function Create() {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        color: '#3B82F6',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(route('tags.store', { workspace: workspace.uuid }));
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Nova Tag</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Crie uma tag para usar em transações
                    </p>
                </div>

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Dados da Tag</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nome</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Ex: Urgente, Viagem, Recorrente"
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Cor</Label>
                                <ColorPicker
                                    value={data.color}
                                    onChange={(color) => setData('color', color)}
                                />
                                {errors.color && (
                                    <p className="text-sm text-destructive">{errors.color}</p>
                                )}
                            </div>

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Criar Tag
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('tags.index', {
                                            workspace: workspace.uuid,
                                        })}
                                    >
                                        Cancelar
                                    </Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
