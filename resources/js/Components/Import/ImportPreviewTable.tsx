import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Check, ChevronDown } from 'lucide-react';
import { formatCurrency } from '@/lib/format-currency';
import type {
    CategoryOption,
    ImportPreviewItem,
    TagOption,
} from '@/types/import';

interface Props {
    items: ImportPreviewItem[];
    onItemsChange: (items: ImportPreviewItem[]) => void;
    categories: CategoryOption[];
    tags: TagOption[];
}

function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');
}

/**
 * Editable preview of a parsed import file.
 *
 * The checkbox controls the `confirm_duplicate` flag, which doubles as the
 * "include in import" selection: the page seeds non-duplicate rows with
 * `confirm_duplicate: true` and duplicate rows with `false`, then only submits
 * the rows whose flag is still true. Duplicate rows are highlighted so the user
 * confirms them explicitly.
 *
 * Each row shows the AI-classified type (debit/credit), lets the user pick a
 * category (filtered by type) and tags (multi-select), and edit the raw values.
 */
export default function ImportPreviewTable({
    items,
    onItemsChange,
    categories,
    tags,
}: Props) {
    function updateItem<K extends keyof ImportPreviewItem>(
        id: string,
        field: K,
        value: ImportPreviewItem[K],
    ): void {
        onItemsChange(
            items.map((item) =>
                item.id === id ? { ...item, [field]: value } : item,
            ),
        );
    }

    function getFilteredCategories(itemType: string): CategoryOption[] {
        const needed = itemType === 'credit' ? 'income' : 'expense';
        return categories.filter(
            (cat) => cat.type === needed || cat.type === 'both',
        );
    }

    function getValueClassName(itemType: string): string {
        return itemType === 'credit' ? 'text-emerald-600' : 'text-destructive';
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-10" />
                    <TableHead>Descrição</TableHead>
                    <TableHead>Valor</TableHead>
                    <TableHead>Data</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Categoria</TableHead>
                    <TableHead>Tags</TableHead>
                    <TableHead>Duplicata</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {items.map((item) => (
                    <TableRow
                        key={item.id}
                        className={
                            item.is_duplicate ? 'bg-amber-50/60' : undefined
                        }
                    >
                        <TableCell>
                            <Checkbox
                                checked={item.confirm_duplicate}
                                onCheckedChange={(checked) =>
                                    updateItem(
                                        item.id,
                                        'confirm_duplicate',
                                        checked === true,
                                    )
                                }
                                aria-label={`Incluir ${item.description}`}
                            />
                        </TableCell>
                        <TableCell>
                            <Input
                                value={item.description}
                                onChange={(e) =>
                                    updateItem(
                                        item.id,
                                        'description',
                                        e.target.value,
                                    )
                                }
                                className="min-w-48"
                            />
                        </TableCell>
                        <TableCell>
                            <Input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={item.value}
                                onChange={(e) =>
                                    updateItem(
                                        item.id,
                                        'value',
                                        Number(e.target.value),
                                    )
                                }
                                className="w-32"
                            />
                            <p
                                className={`mt-1 text-xs whitespace-nowrap ${getValueClassName(item.type)}`}
                            >
                                {formatCurrency(item.value)}
                            </p>
                        </TableCell>
                        <TableCell>
                            <Input
                                type="date"
                                value={item.date}
                                onChange={(e) =>
                                    updateItem(item.id, 'date', e.target.value)
                                }
                                className="w-40"
                            />
                            <p className="mt-1 text-xs text-muted-foreground whitespace-nowrap">
                                {formatDate(item.date)}
                            </p>
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="outline"
                                className={
                                    item.type === 'credit'
                                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                        : 'border-destructive/50 bg-destructive/10 text-destructive'
                                }
                            >
                                {item.type === 'credit' ? 'Receita' : 'Despesa'}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <Select
                                value={item.category_uuid ?? ''}
                                onValueChange={(value) => {
                                    const category = categories.find(
                                        (c) => c.uuid === value,
                                    );
                                    updateItem(
                                        item.id,
                                        'category_uuid',
                                        category?.uuid ?? null,
                                    );
                                    updateItem(
                                        item.id,
                                        'category_name',
                                        category?.name ?? '',
                                    );
                                }}
                            >
                                <SelectTrigger className="min-w-40">
                                    <SelectValue placeholder="Selecionar..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {getFilteredCategories(item.type).map(
                                        (cat) => (
                                            <SelectItem
                                                key={cat.uuid}
                                                value={cat.uuid}
                                            >
                                                {cat.name}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </TableCell>
                        <TableCell>
                            <TagMultiSelect
                                availableTags={tags}
                                selectedTagUuids={item.tags}
                                onTagsChange={(newTags) => {
                                    updateItem(item.id, 'tags', newTags);
                                    const names = newTags
                                        .map(
                                            (uuid) =>
                                                tags.find(
                                                    (t) => t.uuid === uuid,
                                                )?.name ?? '',
                                        )
                                        .filter(Boolean);
                                    updateItem(item.id, 'tag_names', names);
                                }}
                            />
                        </TableCell>
                        <TableCell>
                            {item.is_duplicate ? (
                                <Badge
                                    variant="outline"
                                    className="border-amber-500 bg-amber-100 text-amber-800"
                                >
                                    Possível duplicata
                                </Badge>
                            ) : null}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function TagMultiSelect({
    availableTags,
    selectedTagUuids,
    onTagsChange,
}: {
    availableTags: TagOption[];
    selectedTagUuids: string[];
    onTagsChange: (tags: string[]) => void;
}) {
    function toggleTag(uuid: string): void {
        if (selectedTagUuids.includes(uuid)) {
            onTagsChange(selectedTagUuids.filter((t) => t !== uuid));
        } else {
            onTagsChange([...selectedTagUuids, uuid]);
        }
    }

    const selectedTags = availableTags.filter((t) =>
        selectedTagUuids.includes(t.uuid),
    );

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="flex items-center gap-1 min-w-32 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm hover:bg-accent/50"
                >
                    {selectedTags.length === 0 ? (
                        <span className="text-muted-foreground">
                            Selecionar...
                        </span>
                    ) : (
                        <div className="flex flex-wrap gap-1">
                            {selectedTags.map((tag) => (
                                <Badge
                                    key={tag.uuid}
                                    variant="outline"
                                    style={{
                                        borderColor: `${tag.color}40`,
                                        backgroundColor: `${tag.color}20`,
                                        color: tag.color,
                                    }}
                                    className="text-xs"
                                >
                                    {tag.name}
                                </Badge>
                            ))}
                        </div>
                    )}
                    <ChevronDown className="ml-auto h-3 w-3 shrink-0 opacity-50" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-56 p-0" align="start">
                <Command>
                    <CommandInput placeholder="Buscar tag..." />
                    <CommandList>
                        <CommandEmpty>Nenhuma tag encontrada.</CommandEmpty>
                        <CommandGroup>
                            {availableTags.map((tag) => {
                                const isSelected = selectedTagUuids.includes(
                                    tag.uuid,
                                );
                                return (
                                    <CommandItem
                                        key={tag.uuid}
                                        onSelect={() => toggleTag(tag.uuid)}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox checked={isSelected} />
                                        <span
                                            className="inline-block h-2.5 w-2.5 rounded-full"
                                            style={{
                                                backgroundColor: tag.color,
                                            }}
                                        />
                                        <span>{tag.name}</span>
                                        {isSelected && (
                                            <Check className="ml-auto h-4 w-4" />
                                        )}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
