import { Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ColorPicker } from '@/components/ui/color-picker';

export default function Create() {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'expense',
        color: '#9CA3AF',
        icon: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(route('categories.store', { workspace: workspace.uuid }));
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Nova Categoria</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Crie uma categoria para organizar suas transações
                    </p>
                </div>

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Dados da Categoria</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nome</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Ex: Alimentação, Transporte"
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="type">Tipo</Label>
                                <Select
                                    value={data.type}
                                    onValueChange={(value) => setData('type', value)}
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue placeholder="Selecione o tipo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="income">Receita</SelectItem>
                                        <SelectItem value="expense">Despesa</SelectItem>
                                        <SelectItem value="both">Ambos</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.type && (
                                    <p className="text-sm text-destructive">{errors.type}</p>
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

                            <div className="space-y-2">
                                <Label htmlFor="icon">Ícone</Label>
                                <Input
                                    id="icon"
                                    value={data.icon}
                                    onChange={(e) => setData('icon', e.target.value)}
                                    placeholder="Ex: shopping-cart, home, car"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Nome do ícone Lucide (opcional)
                                </p>
                                {errors.icon && (
                                    <p className="text-sm text-destructive">{errors.icon}</p>
                                )}
                            </div>

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Criar Categoria
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('categories.index', {
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
