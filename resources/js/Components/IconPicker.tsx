import { useEffect, useState } from 'react';
import { ChevronDown, Plus, Search, SearchX } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import DynamicIcon from './DynamicIcon';
import { ICON_CATALOG, type IconDefinition } from '@/lib/icon-catalog';
import { cn } from '@/lib/utils';
import { useInfiniteScroll } from '@/hooks/use-infinite-scroll';

const PAGE_SIZE = 24;
const SEARCH_DEBOUNCE_MS = 150;

interface IconPickerProps {
    value: string | null;
    onChange: (iconKey: string | null) => void;
    placeholder?: string;
}

export default function IconPicker({
    value,
    onChange,
    placeholder = 'Selecionar ícone',
}: IconPickerProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [debouncedQuery, setDebouncedQuery] = useState('');
    const [visibleCount, setVisibleCount] = useState(PAGE_SIZE);

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            setDebouncedQuery(query);
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeoutId);
    }, [query]);

    useEffect(() => {
        setVisibleCount(PAGE_SIZE);
    }, [debouncedQuery]);

    const normalizedQuery = debouncedQuery.toLowerCase();

    const filtered: IconDefinition[] = ICON_CATALOG.filter(
        (icon) =>
            icon.key.toLowerCase().includes(normalizedQuery) ||
            icon.name.toLowerCase().includes(normalizedQuery),
    );

    const sentinelRef = useInfiniteScroll({
        hasMore: visibleCount < filtered.length,
        onLoadMore: () => setVisibleCount((prev) => prev + PAGE_SIZE),
    });

    const selectedIcon = ICON_CATALOG.find((i) => i.key === value);

    return (
        <div className="flex flex-col gap-1">
            <Popover open={isOpen} onOpenChange={setIsOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        data-testid="icon-picker-trigger"
                        aria-expanded={isOpen}
                        aria-label={
                            value ? (selectedIcon?.name ?? value) : placeholder
                        }
                        className="size-10 p-0 relative"
                    >
                        {value && value !== '' ? (
                            <DynamicIcon name={value} size={20} />
                        ) : (
                            <Plus size={20} className="text-muted-foreground" />
                        )}
                        <ChevronDown className="absolute -bottom-1 -right-1 size-3.5 rounded-full bg-background border text-muted-foreground" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-80 p-2" align="start">
                    <div className="space-y-2">
                        <div className="relative px-1">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Buscar ícone..."
                                className="pl-9"
                            />
                        </div>
                        <div className="h-64 overflow-y-auto">
                            {filtered.length === 0 ? (
                                <div className="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
                                    <SearchX size={24} />
                                    <p className="text-sm">
                                        Nenhum ícone encontrado
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <div className="grid grid-cols-4 gap-1">
                                        {filtered
                                            .slice(0, visibleCount)
                                            .map((icon) => (
                                                <Button
                                                    key={icon.key}
                                                    variant="ghost"
                                                    size="icon"
                                                    className={cn(
                                                        'size-12 rounded-lg',
                                                        value === icon.key &&
                                                            'ring-2 ring-primary bg-accent',
                                                    )}
                                                    onClick={() => {
                                                        onChange(icon.key);
                                                        setIsOpen(false);
                                                    }}
                                                    aria-label={icon.name}
                                                    aria-pressed={
                                                        value === icon.key
                                                    }
                                                >
                                                    <icon.component size={20} />
                                                </Button>
                                            ))}
                                    </div>
                                    {visibleCount < filtered.length && (
                                        <div
                                            ref={sentinelRef}
                                            className="flex justify-center py-2"
                                        >
                                            <div className="flex gap-1">
                                                {[...Array(4)].map((_, i) => (
                                                    <div
                                                        key={i}
                                                        className="size-8 animate-pulse rounded-md bg-muted"
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </PopoverContent>
            </Popover>
            {selectedIcon && (
                <p className="text-xs text-muted-foreground">
                    {selectedIcon.name}
                </p>
            )}
        </div>
    );
}
