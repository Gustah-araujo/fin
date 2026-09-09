import { router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import MonthDetailModal from '@/Components/Planning/MonthDetailModal';
import { useWorkspace } from '@/hooks/useWorkspace';
import { formatCurrency } from '@/lib/format-currency';
import type { MonthProjection, Totals, PlanningFilter } from '@/types/planning';

interface Props {
    projection: MonthProjection[];
    date_start: string;
    date_end: string;
    totals: Totals;
    filter: PlanningFilter;
}

type PeriodPreset =
    'next-1' | 'next-3' | 'next-6' | 'next-9' | 'next-12' | 'custom';

const PERIOD_PRESETS: { label: string; value: PeriodPreset; months: number }[] =
    [
        { label: 'Próximo mês', value: 'next-1', months: 1 },
        { label: 'Próximos 3 meses', value: 'next-3', months: 3 },
        { label: 'Próximos 6 meses', value: 'next-6', months: 6 },
        { label: 'Próximos 9 meses', value: 'next-9', months: 9 },
        { label: 'Próximos 12 meses', value: 'next-12', months: 12 },
    ];

const FILTER_OPTIONS: { label: string; value: PlanningFilter }[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Apenas Gastos', value: 'expenses' },
    { label: 'Apenas Rendas', value: 'incomes' },
];

function toISODate(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function computeDates(
    preset: PeriodPreset,
    customStart: string,
    customEnd: string,
): { date_start: string; date_end: string } {
    if (preset === 'custom') {
        return { date_start: customStart, date_end: customEnd };
    }
    const def = PERIOD_PRESETS.find((p) => p.value === preset);
    if (!def)
        return {
            date_start: toISODate(new Date()),
            date_end: toISODate(new Date()),
        };

    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    const end = new Date(start);
    end.setMonth(end.getMonth() + def.months);
    end.setDate(end.getDate() - 1);

    return { date_start: toISODate(start), date_end: toISODate(end) };
}

function detectPreset(dateStart: string, dateEnd: string): PeriodPreset {
    const now = new Date();
    const expectedStart = toISODate(
        new Date(now.getFullYear(), now.getMonth(), 1),
    );

    if (dateStart !== expectedStart) return 'custom';

    for (const preset of PERIOD_PRESETS) {
        const expected = computeDates(preset.value, '', '');
        if (dateEnd === expected.date_end) return preset.value;
    }

    return 'custom';
}

export default function PlanningIndex({
    projection,
    date_start,
    date_end,
    totals,
    filter: initialFilter,
}: Props) {
    const workspace = useWorkspace();

    const initialPreset = useMemo(
        () => detectPreset(date_start, date_end),
        [date_start, date_end],
    );

    const [selectedPreset, setSelectedPreset] =
        useState<PeriodPreset>(initialPreset);
    const [customStart, setCustomStart] = useState(date_start);
    const [customEnd, setCustomEnd] = useState(date_end);
    const [filter, setFilter] = useState<PlanningFilter>(initialFilter);
    const [modalMonth, setModalMonth] = useState<string | null>(null);

    const isCustom = selectedPreset === 'custom';

    const handlePresetChange = useCallback(
        (value: string) => {
            const preset = value as PeriodPreset;
            setSelectedPreset(preset);

            if (preset !== 'custom') {
                const dates = computeDates(preset, '', '');
                router.get(
                    route('planning.index', { workspace: workspace.uuid }),
                    {
                        date_start: dates.date_start,
                        date_end: dates.date_end,
                        type: filter,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        only: ['projection', 'totals'],
                    },
                );
            }
        },
        [workspace.uuid, filter],
    );

    const handleCustomDateChange = useCallback(
        (which: 'start' | 'end', value: string) => {
            const updated =
                which === 'start'
                    ? { customStart: value, customEnd }
                    : { customStart, customEnd: value };

            if (which === 'start') setCustomStart(value);
            else setCustomEnd(value);

            if (updated.customStart && updated.customEnd) {
                router.get(
                    route('planning.index', { workspace: workspace.uuid }),
                    {
                        date_start: updated.customStart,
                        date_end: updated.customEnd,
                        type: filter,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        only: ['projection', 'totals'],
                    },
                );
            }
        },
        [workspace.uuid, filter, customStart, customEnd],
    );

    const handleFilterChange = useCallback((value: string) => {
        const newFilter = value as PlanningFilter;
        setFilter(newFilter);
        // No backend reload needed — filter is client-side
    }, []);

    const displayTotals = useMemo(() => {
        if (filter === 'all') return totals;
        if (filter === 'expenses') {
            return {
                expenses: totals.expenses,
                incomes: 0,
                balance: -totals.expenses,
            };
        }
        return {
            expenses: 0,
            incomes: totals.incomes,
            balance: totals.incomes,
        };
    }, [totals, filter]);

    const showExpenses = filter !== 'incomes';
    const showIncomes = filter !== 'expenses';

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Planejamento Futuro
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Projeção de gastos e rendas
                        </p>
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        {/* Period Selector */}
                        <div className="flex flex-col gap-1.5">
                            <Label className="text-xs text-muted-foreground">
                                Período
                            </Label>
                            <Select
                                value={selectedPreset}
                                onValueChange={handlePresetChange}
                            >
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Selecionar período" />
                                </SelectTrigger>
                                <SelectContent>
                                    {PERIOD_PRESETS.map((preset) => (
                                        <SelectItem
                                            key={preset.value}
                                            value={preset.value}
                                        >
                                            {preset.label}
                                        </SelectItem>
                                    ))}
                                    <SelectItem value="custom">
                                        Personalizado
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Custom Date Pickers */}
                        {isCustom && (
                            <>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs text-muted-foreground">
                                        Data início
                                    </Label>
                                    <input
                                        type="date"
                                        value={customStart}
                                        onChange={(e) =>
                                            handleCustomDateChange(
                                                'start',
                                                e.target.value,
                                            )
                                        }
                                        className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs text-muted-foreground">
                                        Data fim
                                    </Label>
                                    <input
                                        type="date"
                                        value={customEnd}
                                        onChange={(e) =>
                                            handleCustomDateChange(
                                                'end',
                                                e.target.value,
                                            )
                                        }
                                        className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                </div>
                            </>
                        )}

                        {/* Filter Toggle */}
                        <div className="flex flex-col gap-1.5">
                            <Label className="text-xs text-muted-foreground">
                                Filtrar
                            </Label>
                            <div className="flex">
                                {FILTER_OPTIONS.map((option) => (
                                    <Button
                                        key={option.value}
                                        variant={
                                            filter === option.value
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        className="rounded-none first:rounded-l-md last:rounded-r-md"
                                        onClick={() =>
                                            handleFilterChange(option.value)
                                        }
                                    >
                                        {option.label}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Totals Cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    {showExpenses && (
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    Gastos Comprometidos
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-bold text-destructive">
                                    {formatCurrency(displayTotals.expenses)}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {showIncomes && (
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    Rendas Programadas
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-bold text-emerald-600">
                                    {formatCurrency(displayTotals.incomes)}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Saldo Projetado
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className={`text-2xl font-bold ${
                                    displayTotals.balance < 0
                                        ? 'text-destructive'
                                        : 'text-emerald-600'
                                }`}
                            >
                                {formatCurrency(displayTotals.balance)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Projection Table */}
                {projection.every(
                    (row) => row.expenses === 0 && row.incomes === 0,
                ) ? (
                    <div className="flex flex-col items-center gap-4 py-12">
                        <p className="text-sm text-muted-foreground">
                            Nenhuma transação encontrada no período selecionado
                        </p>
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Mês</TableHead>
                                    {showExpenses && (
                                        <TableHead className="text-right">
                                            Gastos
                                        </TableHead>
                                    )}
                                    {showIncomes && (
                                        <TableHead className="text-right">
                                            Rendas
                                        </TableHead>
                                    )}
                                    <TableHead className="text-right">
                                        Saldo
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {projection.map((row) => (
                                    <TableRow
                                        key={row.month}
                                        className="cursor-pointer"
                                        onClick={() => setModalMonth(row.month)}
                                    >
                                        <TableCell className="font-medium">
                                            {row.month_label}
                                        </TableCell>
                                        {showExpenses && (
                                            <TableCell className="text-right text-destructive font-semibold whitespace-nowrap">
                                                {formatCurrency(row.expenses)}
                                            </TableCell>
                                        )}
                                        {showIncomes && (
                                            <TableCell className="text-right text-emerald-600 font-semibold whitespace-nowrap">
                                                {formatCurrency(row.incomes)}
                                            </TableCell>
                                        )}
                                        <TableCell
                                            className={`text-right font-semibold whitespace-nowrap ${
                                                row.balance < 0
                                                    ? 'text-destructive'
                                                    : 'text-emerald-600'
                                            }`}
                                        >
                                            {formatCurrency(row.balance)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {/* Month Detail Modal */}
                {modalMonth && (
                    <MonthDetailModal
                        month={modalMonth}
                        isOpen={!!modalMonth}
                        onClose={() => setModalMonth(null)}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
