import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatMonthLabel, navigateMonth } from '@/lib/month';

interface MonthPickerProps {
    month: string; // YYYY-MM format
    onChange: (month: string) => void;
}

export default function MonthPicker({ month, onChange }: MonthPickerProps) {
    return (
        <div className="flex items-center gap-2">
            <Button
                variant="outline"
                size="icon"
                onClick={() => onChange(navigateMonth(month, -1))}
            >
                <ChevronLeft />
            </Button>
            <span className="text-sm font-medium min-w-[160px] text-center">
                {formatMonthLabel(month)}
            </span>
            <Button
                variant="outline"
                size="icon"
                onClick={() => onChange(navigateMonth(month, 1))}
            >
                <ChevronRight />
            </Button>
        </div>
    );
}
