import { Label } from '@/components/ui/label';

interface TagItem {
    uuid: string;
    name: string;
    color: string;
}

interface TagsSelectorProps {
    tags: TagItem[];
    selected: string[];
    onToggle: (uuid: string) => void;
    error?: string;
}

export function TagsSelector({
    tags,
    selected,
    onToggle,
    error,
}: TagsSelectorProps) {
    if (tags.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            <Label>Tags</Label>
            <div className="flex flex-wrap gap-2">
                {tags.map((tag) => (
                    <div
                        key={tag.uuid}
                        className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-md border cursor-pointer text-sm transition-colors ${
                            selected.includes(tag.uuid)
                                ? 'bg-primary text-primary-foreground border-primary'
                                : 'bg-background hover:bg-accent'
                        }`}
                        onClick={() => onToggle(tag.uuid)}
                    >
                        <span
                            className="w-2.5 h-2.5 rounded-full"
                            style={{ backgroundColor: tag.color }}
                        />
                        {tag.name}
                    </div>
                ))}
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
