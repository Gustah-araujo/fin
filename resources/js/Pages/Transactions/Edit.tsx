import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useWorkspace } from '@/hooks/useWorkspace';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ValueField } from '@/Components/Transactions/ValueField';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface AccountItem {
    uuid: string;
    name: string;
    type: string;
    current_balance: number;
}

interface CategoryItem {
    uuid: string;
    name: string;
    type: string;
    color: string;
    icon: string | null;
}

interface TagItem {
    uuid: string;
    name: string;
    color: string;
}

interface CardItem {
    uuid: string;
    name: string;
    credit_limit: number;
    available_limit: number;
}

interface TransactionItem {
    uuid: string;
    description: string;
    value: number;
    date: string;
    paid_at: string | null;
    account: AccountItem | null;
    credit_card: CardItem | null;
    credit_card_id: string | null;
    category: CategoryItem | null;
    tags: TagItem[];
    is_installment: boolean;
    installment_label: string | null;
}

interface Props {
    transaction: TransactionItem;
    accounts: AccountItem[];
    categories: CategoryItem[];
    tags: TagItem[];
}

function PaymentSection({
    transaction,
    isCard,
    scope,
    setScope,
    data,
    setData,
    accounts,
    errors,
}: {
    transaction: TransactionItem;
    isCard: boolean;
    scope: string;
    setScope: (v: 'single' | 'group') => void;
    data: { account_id: string };
    setData: (key: string, value: string) => void;
    accounts: AccountItem[];
    errors: Record<string, string>;
}) {
    if (isCard) {
        return (
            <>
                <div className="space-y-2">
                    <Label>Cartão</Label>
                    <div className="rounded-md border px-3 py-2 text-sm bg-muted">
                        {transaction.credit_card?.name}
                        {transaction.is_installment &&
                            transaction.installment_label && (
                                <span className="ml-2 text-muted-foreground">
                                    — Parcela {transaction.installment_label}
                                </span>
                            )}
                    </div>
                </div>

                {transaction.is_installment && (
                    <div className="space-y-2">
                        <Label>Escopo da edição</Label>
                        <RadioGroup
                            value={scope}
                            onValueChange={(v) =>
                                setScope(v as 'single' | 'group')
                            }
                        >
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem
                                    value="single"
                                    id="scope-single"
                                />
                                <Label
                                    htmlFor="scope-single"
                                    className="text-sm font-normal"
                                >
                                    Apenas esta parcela
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem
                                    value="group"
                                    id="scope-group"
                                />
                                <Label
                                    htmlFor="scope-group"
                                    className="text-sm font-normal"
                                >
                                    Esta e futuras parcelas
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>
                )}
            </>
        );
    }

    return (
        <div className="space-y-2">
            <Label htmlFor="account_id">Conta</Label>
            <Select
                value={data.account_id}
                onValueChange={(value) => setData('account_id', value)}
            >
                <SelectTrigger id="account_id">
                    <SelectValue placeholder="Selecione a conta" />
                </SelectTrigger>
                <SelectContent>
                    {accounts.map((account) => (
                        <SelectItem key={account.uuid} value={account.uuid}>
                            {account.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {errors.account_id && (
                <p className="text-sm text-destructive">{errors.account_id}</p>
            )}
        </div>
    );
}

export default function Edit({
    transaction,
    accounts,
    categories,
    tags,
}: Props) {
    const workspace = useWorkspace();
    const isCard = !!transaction.credit_card_id;

    const [scope, setScope] = useState<'single' | 'group'>('single');

    const form = useForm({
        description: transaction.description,
        value: String(transaction.value),
        date: transaction.date,
        account_id: transaction.account?.uuid ?? '',
        credit_card_id: transaction.credit_card?.uuid ?? '',
        installments: 1,
        total_value: '',
        category_id: transaction.category?.uuid ?? '',
        tags: transaction.tags.map((t) => t.uuid),
    });

    const { data, setData, put, processing, errors } = form;

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        const payload: Record<string, unknown> = {
            description: data.description,
            value: data.value,
            date: data.date,
            category_id: data.category_id,
            tags: data.tags,
        };

        if (isCard) {
            payload.scope = scope;
        } else {
            payload.account_id = data.account_id;
        }

        put(
            route('transactions.update', {
                workspace: workspace.uuid,
                transaction: transaction.uuid,
            }),
            payload,
        );
    }

    function toggleTag(uuid: string) {
        if (data.tags.includes(uuid)) {
            setData(
                'tags',
                data.tags.filter((t) => t !== uuid),
            );
        } else {
            setData('tags', [...data.tags, uuid]);
        }
    }

    const isPaid = transaction.paid_at !== null;
    const paidDate = isPaid
        ? new Date(transaction.paid_at!).toLocaleDateString('pt-BR')
        : null;

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Editar Despesa
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Altere os dados da despesa
                    </p>
                </div>

                {isPaid && (
                    <Card className="border-amber-200 bg-amber-50 max-w-lg">
                        <CardContent className="py-3">
                            <p className="text-sm text-amber-800">
                                Esta despesa foi paga em {paidDate}. Alterar o
                                valor ou conta recalculará o saldo.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Dados da Despesa</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="description">Descrição</Label>
                                <Input
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            <ValueField
                                hidden={isCard && transaction.is_installment}
                                value={data.value}
                                error={errors.value}
                                onChange={(value) => setData('value', value)}
                            />

                            <div className="space-y-2">
                                <Label htmlFor="date">Data</Label>
                                <Input
                                    id="date"
                                    type="date"
                                    value={data.date}
                                    onChange={(e) =>
                                        setData('date', e.target.value)
                                    }
                                />
                                {errors.date && (
                                    <p className="text-sm text-destructive">
                                        {errors.date}
                                    </p>
                                )}
                            </div>

                            <PaymentSection
                                transaction={transaction}
                                isCard={isCard}
                                scope={scope}
                                setScope={setScope}
                                data={data}
                                setData={setData}
                                accounts={accounts}
                                errors={errors}
                            />

                            <div className="space-y-2">
                                <Label htmlFor="category_id">Categoria</Label>
                                <Select
                                    value={data.category_id}
                                    onValueChange={(value) =>
                                        setData('category_id', value)
                                    }
                                >
                                    <SelectTrigger id="category_id">
                                        <SelectValue placeholder="Selecione a categoria" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem
                                                key={category.uuid}
                                                value={category.uuid}
                                            >
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.category_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.category_id}
                                    </p>
                                )}
                            </div>

                            {tags.length > 0 && (
                                <div className="space-y-2">
                                    <Label>Tags</Label>
                                    <div className="flex flex-wrap gap-2">
                                        {tags.map((tag) => (
                                            <div
                                                key={tag.uuid}
                                                className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-md border cursor-pointer text-sm transition-colors ${
                                                    data.tags.includes(tag.uuid)
                                                        ? 'bg-primary text-primary-foreground border-primary'
                                                        : 'bg-background hover:bg-accent'
                                                }`}
                                                onClick={() =>
                                                    toggleTag(tag.uuid)
                                                }
                                            >
                                                <span
                                                    className="w-2.5 h-2.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            tag.color,
                                                    }}
                                                />
                                                {tag.name}
                                            </div>
                                        ))}
                                    </div>
                                    {errors.tags && (
                                        <p className="text-sm text-destructive">
                                            {errors.tags}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('transactions.index', {
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
