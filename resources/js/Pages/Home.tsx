import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CategoriesChart from '@/Components/Dashboard/CategoriesChart';
import ExpenseGroupsChart from '@/Components/Dashboard/ExpenseGroupsChart';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/format-currency';

interface ExpenseGroupSlice {
    uuid: string | null;
    name: string;
    color: string;
    total: number;
    percent: number;
}

interface CategorySlice {
    uuid: string | null;
    name: string;
    color: string;
    group_color: string | null;
    group_name: string | null;
    total: number;
    percent: number;
}

interface Kpis {
    balance: number;
    income: number;
    expenses: number;
    pending_count: number;
    pending_value: number;
}

interface Props {
    month: string;
    kpis: Kpis;
    expenseGroups: ExpenseGroupSlice[];
    categories: CategorySlice[];
    hasGroups: boolean;
}

export default function Home({
    kpis,
    expenseGroups,
    categories,
    hasGroups,
}: Props) {
    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Dashboard
                </h1>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Saldo Total
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {formatCurrency(kpis.balance)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Receitas do Mês
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold text-emerald-600">
                                {formatCurrency(kpis.income)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Despesas do Mês
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold text-rose-600">
                                {formatCurrency(kpis.expenses)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Despesas Pendentes
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold text-amber-600">
                                {kpis.pending_count}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {formatCurrency(kpis.pending_value)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <ExpenseGroupsChart
                        groups={expenseGroups}
                        hasGroups={hasGroups}
                    />
                    <CategoriesChart categories={categories} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
