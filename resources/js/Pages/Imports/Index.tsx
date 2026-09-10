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

type ImportType = 'expense' | 'income';

interface UploadFormData {
    type: ImportType;
    file: File | null;
}

interface ConfirmFormData {
    type: ImportType;
    items: ImportPreviewItem[];
}

export default function Index({ type, preview, flash }: ImportPageProps) {
    const workspace = useWorkspace();

    const isIncome = type === 'income';
    const label = isIncome ? 'Receitas' : 'Despesas';
    const storeRoute = isIncome
        ? 'incomes.import.store'
        : 'transactions.import.store';
    const confirmRoute = isIncome
        ? 'incomes.import.confirm'
        : 'transactions.import.confirm';
    const indexRoute = isIncome ? 'incomes.index' : 'transactions.index';

    const uploadForm = useForm<UploadFormData>({
        type,
        file: null,
    });

    const confirmForm = useForm<ConfirmFormData>({
        type,
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
        uploadForm.post(route(storeRoute, { workspace: workspace.uuid }), {
            forceFormData: true,
        });
    }

    function handleConfirm(e: React.FormEvent): void {
        e.preventDefault();

        const selectedItems = confirmForm.data.items.filter(
            (item) => item.confirm_duplicate,
        );

        confirmForm.transform((data) => ({
            type: data.type,
            items: selectedItems,
        }));

        confirmForm.post(route(confirmRoute, { workspace: workspace.uuid }));
    }

    function handleItemsChange(items: ImportPreviewItem[]): void {
        setConfirmData('items', items);
    }

    if (!preview) {
        return (
            <AuthenticatedLayout>
                <div className="space-y-6">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Importar {label}
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Envie um arquivo CSV para importar{' '}
                            {label.toLowerCase()} automaticamente.
                        </p>
                    </div>

                    <Card className="max-w-lg">
                        <CardHeader>
                            <CardTitle>Arquivo CSV</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleUpload} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="file">Arquivo</Label>
                                    <Input
                                        id="file"
                                        type="file"
                                        accept=".csv,text/csv"
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
                                            href={route(indexRoute, {
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
                            Importar {label}
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
                                    href={route(indexRoute, {
                                        workspace: workspace.uuid,
                                    })}
                                >
                                    Voltar para {label}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </AuthenticatedLayout>
        );
    }

    const selectedCount = confirmForm.data.items.filter(
        (item) => item.confirm_duplicate,
    ).length;

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Importar {label}
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Revise os dados antes de confirmar a importação.
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {selectedCount} de {confirmForm.data.items.length}{' '}
                        selecionadas
                    </Badge>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <ImportPreviewTable
                            items={confirmForm.data.items}
                            onItemsChange={handleItemsChange}
                            type={type}
                        />
                    </CardContent>
                </Card>

                <form
                    onSubmit={handleConfirm}
                    className="flex items-center gap-3"
                >
                    <Button
                        type="submit"
                        disabled={confirmForm.processing || selectedCount === 0}
                    >
                        Confirmar Importação
                    </Button>
                    <Button variant="outline" asChild>
                        <Link
                            href={route(indexRoute, {
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
