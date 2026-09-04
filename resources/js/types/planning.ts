export interface TransactionDetail {
    id: string;
    description: string;
    value: number;
    category: string | null;
    date: string;
    type: 'expense' | 'income';
}

export interface MonthProjection {
    month: string;
    month_label: string;
    expenses: number;
    incomes: number;
    balance: number;
}

export interface Totals {
    expenses: number;
    incomes: number;
    balance: number;
}

export interface MonthDetail {
    month: string;
    expenses: TransactionDetail[];
    incomes: TransactionDetail[];
}

export type PlanningFilter = 'all' | 'expenses' | 'incomes';
