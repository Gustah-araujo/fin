export function getCurrentMonth(): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');

    return `${year}-${month}`;
}

export function parseMonth(month: string): { year: number; month: number } {
    const [yearPart, monthPart] = month.split('-');

    return {
        year: Number(yearPart),
        month: Number(monthPart),
    };
}

export function formatMonthLabel(month: string): string {
    const { year, month: monthNumber } = parseMonth(month);

    return `${String(monthNumber).padStart(2, '0')}/${year}`;
}

export function navigateMonth(month: string, delta: number): string {
    const { year, month: monthNumber } = parseMonth(month);
    const totalMonths = year * 12 + (monthNumber - 1) + delta;
    const nextYear = Math.floor(totalMonths / 12);
    const nextMonth = (totalMonths % 12) + 1;

    return `${nextYear}-${String(nextMonth).padStart(2, '0')}`;
}
