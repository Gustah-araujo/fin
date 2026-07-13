import { Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ColorPicker } from '@/components/ui/color-picker';

interface TagData {
    uuid: string;
    name: string;
    color: string;
    created_at: string;
}

interface Props {
    tag: TagData;
}

export default function Edit({ tag }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const { data, setData, put, processing, errors } = useForm({
        name: tag.name,
        color: tag.color,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(route('tags.update', { workspace: workspace.uuid, tag: tag.uuid }));
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Editar Tag</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Atualize os dados da tag
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
                                    Salvar
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
