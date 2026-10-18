import { Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ImportPreviewTable from '@/Components/Import/ImportPreviewTable';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useWorkspace } from '@/hooks/useWorkspace';
import type { ImportPageProps, ImportPreviewItem } from '@/types/import';

interface UploadFormData {
    file: File | null;
}

interface ConfirmFormData {
    items: ImportPreviewItem[];
}

export default function Index({
    categories,
    tags,
    preview,
    flash,
}: ImportPageProps) {
    const workspace = useWorkspace();

    const uploadForm = useForm<UploadFormData>({
        file: null,
    });

    const confirmForm = useForm<ConfirmFormData>({
        items: [],
    });

    const { setData: setConfirmData } = confirmForm;

    useEffect(() => {
        if (!preview || preview.length === 0) {
            return;
        }

        setConfirmData(
            'items',
            preview.map((item) => ({
                ...item,
                // Non-duplicates are included by default; duplicates require
                // an explicit confirmation from the user.
                confirm_duplicate: !item.is_duplicate,
            })),
        );
    }, [preview, setConfirmData]);

    function handleUpload(e: React.FormEvent): void {
        e.preventDefault();
        uploadForm.post(route('import.store', { workspace: workspace.uuid }), {
            forceFormData: true,
        });
    }

    function handleConfirm(e: React.FormEvent): void {
        e.preventDefault();

        const selectedItems = confirmForm.data.items.filter(
            (item) => item.confirm_duplicate,
        );

        confirmForm.transform(() => ({
            items: selectedItems,
        }));

        confirmForm.post(
            route('import.confirm', { workspace: workspace.uuid }),
        );
    }

    function handleItemsChange(items: ImportPreviewItem[]): void {
        setConfirmData('items', items);
    }

    const expenseCount = confirmForm.data.items.filter(
        (item) => item.type !== 'credit' && item.confirm_duplicate,
    ).length;
    const incomeCount = confirmForm.data.items.filter(
        (item) => item.type === 'credit' && item.confirm_duplicate,
    ).length;

    if (!preview) {
        return (
            <AuthenticatedLayout>
                <div className="space-y-6">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Importar
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Envie um arquivo CSV, PDF, HTML ou imagem para
                            importar transações automaticamente.
                        </p>
                    </div>

                    <Card className="max-w-lg">
                        <CardHeader>
                            <CardTitle>Arquivo</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleUpload} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="file">Arquivo</Label>
                                    <Input
                                        id="file"
                                        type="file"
                                        accept=".csv,.txt,.pdf,.html,.htm,.png,.jpg,.jpeg,.webp"
                                        onChange={(e) =>
                                            uploadForm.setData(
                                                'file',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    {uploadForm.errors.file && (
                                        <p className="text-sm text-destructive">
                                            {uploadForm.errors.file}
                                        </p>
                                    )}
                                </div>

                                {flash?.error && (
                                    <p className="text-sm text-destructive">
                                        {flash.error}
                                    </p>
                                )}

                                <div className="flex items-center gap-3 pt-2">
                                    <Button
                                        type="submit"
                                        disabled={
                                            uploadForm.processing ||
                                            uploadForm.data.file === null
                                        }
                                    >
                                        Importar
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={route('dashboard', {
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

    if (preview.length === 0) {
        return (
            <AuthenticatedLayout>
                <div className="space-y-6">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Importar
                        </h1>
                    </div>

                    <Card>
                        <CardContent className="flex flex-col items-center justify-center gap-4 py-12">
                            <p className="text-sm text-muted-foreground">
                                {flash?.error ??
                                    'Nenhuma transação encontrada no arquivo.'}
                            </p>
                            <Button variant="outline" asChild>
                                <Link
                                    href={route('dashboard', {
                                        workspace: workspace.uuid,
                                    })}
                                >
                                    Voltar
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Importar
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Revise os dados antes de confirmar a importação.
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {expenseCount} despesas + {incomeCount} receitas
                        selecionadas
                    </Badge>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <ImportPreviewTable
                            items={confirmForm.data.items}
                            onItemsChange={handleItemsChange}
                            categories={categories ?? []}
                            tags={tags ?? []}
                        />
                    </CardContent>
                </Card>

                <form
                    onSubmit={handleConfirm}
                    className="flex items-center gap-3"
                >
                    <Button
                        type="submit"
                        disabled={
                            confirmForm.processing ||
                            expenseCount + incomeCount === 0
                        }
                    >
                        Confirmar Importação
                    </Button>
                    <Button variant="outline" asChild>
                        <Link
                            href={route('dashboard', {
                                workspace: workspace.uuid,
                            })}
                        >
                            Cancelar
                        </Link>
                    </Button>
                    {confirmForm.errors.items && (
                        <p className="text-sm text-destructive">
                            {confirmForm.errors.items}
                        </p>
                    )}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
