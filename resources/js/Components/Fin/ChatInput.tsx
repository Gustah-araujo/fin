import { useRef, useState } from 'react';
import type { DragEvent } from 'react';
import {
    File,
    FileSpreadsheet,
    FileText,
    Image as ImageIcon,
    Paperclip,
    Send,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { toast } from 'sonner';
import { dispatchToast } from '@/lib/toast';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

const ALLOWED_EXTENSIONS = [
    '.pdf',
    '.csv',
    '.xlsx',
    '.xls',
    '.txt',
    '.png',
    '.jpg',
    '.jpeg',
    '.webp',
] as const;

const ACCEPT_ATTRIBUTE = ALLOWED_EXTENSIONS.join(',');
const MAX_FILE_SIZE = 10 * 1024 * 1024;
const MAX_FILES = 5;

interface Props {
    disabled: boolean;
    onSend: (content: string, files: File[]) => void;
}

interface FilterResult {
    accepted: File[];
    rejectedType: number;
    rejectedSize: number;
    overflow: number;
}

function getExtension(name: string): string {
    const index = name.toLowerCase().lastIndexOf('.');
    return index === -1 ? '' : name.slice(index);
}

function isAllowedType(name: string): boolean {
    return (ALLOWED_EXTENSIONS as readonly string[]).includes(
        getExtension(name),
    );
}

function attachmentIcon(name: string): LucideIcon {
    const extension = getExtension(name);

    if (['.png', '.jpg', '.jpeg', '.webp'].includes(extension)) {
        return ImageIcon;
    }
    if (['.csv', '.xlsx', '.xls'].includes(extension)) {
        return FileSpreadsheet;
    }
    if (['.pdf', '.txt'].includes(extension)) {
        return FileText;
    }
    return File;
}

function filterAcceptedFiles(
    incoming: File[],
    stagedCount: number,
): FilterResult {
    const accepted: File[] = [];
    let rejectedType = 0;
    let rejectedSize = 0;

    for (const file of incoming) {
        if (!isAllowedType(file.name)) {
            rejectedType += 1;
            continue;
        }
        if (file.size > MAX_FILE_SIZE) {
            rejectedSize += 1;
            continue;
        }
        if (stagedCount + accepted.length >= MAX_FILES) {
            break;
        }
        accepted.push(file);
    }

    return {
        accepted,
        rejectedType,
        rejectedSize,
        overflow: Math.max(
            incoming.length - rejectedType - rejectedSize - accepted.length,
            0,
        ),
    };
}

function notifyFilterErrors(result: FilterResult): void {
    const errors: string[] = [];
    if (result.rejectedType > 0) {
        errors.push('Tipo de arquivo não permitido.');
    }
    if (result.rejectedSize > 0) {
        errors.push('Arquivo muito grande (máx. 10MB).');
    }
    if (result.overflow > 0) {
        errors.push('Máximo de 5 arquivos por mensagem.');
    }
    for (const message of errors) {
        toast.error(message);
        dispatchToast('error', message);
    }
}

function AttachmentChip({
    file,
    onRemove,
}: {
    file: File;
    onRemove: () => void;
}) {
    const Icon = attachmentIcon(file.name);

    return (
        <span
            data-testid="fin-attachment-chip"
            className="flex items-center gap-1.5 rounded-md border bg-accent px-2 py-1 text-xs"
        >
            <Icon className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
            <span className="max-w-48 truncate font-medium">{file.name}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={`Remover anexo ${file.name}`}
                className="h-4 w-4"
                onClick={onRemove}
            >
                <X className="h-3 w-3" />
            </Button>
        </span>
    );
}

export default function ChatInput({ disabled, onSend }: Props) {
    const [value, setValue] = useState('');
    const [stagedFiles, setStagedFiles] = useState<File[]>([]);
    const [isDragging, setIsDragging] = useState(false);
    const dragDepth = useRef(0);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const canSend = value.trim().length > 0 || stagedFiles.length > 0;

    const addFiles = (incoming: File[]): void => {
        if (incoming.length === 0) {
            return;
        }

        const result = filterAcceptedFiles(incoming, stagedFiles.length);
        notifyFilterErrors(result);

        if (result.accepted.length > 0) {
            setStagedFiles((prev) => [...prev, ...result.accepted]);
        }
    };

    const removeFile = (target: File): void => {
        setStagedFiles((prev) => prev.filter((file) => file !== target));
    };

    const handleSend = (): void => {
        if (disabled || !canSend) {
            return;
        }

        onSend(value.trim(), stagedFiles);
        setValue('');
        setStagedFiles([]);
    };

    const handleKeyDown = (
        event: React.KeyboardEvent<HTMLTextAreaElement>,
    ): void => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            handleSend();
        }
    };

    const handleSelectFiles = (
        event: React.ChangeEvent<HTMLInputElement>,
    ): void => {
        addFiles(Array.from(event.target.files ?? []));
        event.target.value = '';
    };

    const handleDragEnter = (event: DragEvent<HTMLDivElement>): void => {
        event.preventDefault();
        dragDepth.current += 1;
        setIsDragging(true);
    };

    const handleDragLeave = (event: DragEvent<HTMLDivElement>): void => {
        event.preventDefault();
        dragDepth.current = Math.max(dragDepth.current - 1, 0);
        if (dragDepth.current === 0) {
            setIsDragging(false);
        }
    };

    const handleDrop = (event: DragEvent<HTMLDivElement>): void => {
        event.preventDefault();
        dragDepth.current = 0;
        setIsDragging(false);
        addFiles(Array.from(event.dataTransfer.files));
    };

    return (
        <div
            className="relative border-t p-3"
            onDragEnter={handleDragEnter}
            onDragOver={(event) => event.preventDefault()}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
        >
            {stagedFiles.length > 0 && (
                <div className="flex flex-wrap gap-2 pb-2">
                    {stagedFiles.map((file) => (
                        <AttachmentChip
                            key={`${file.name}-${file.lastModified}-${file.size}`}
                            file={file}
                            onRemove={() => removeFile(file)}
                        />
                    ))}
                </div>
            )}

            {isDragging && (
                <div
                    data-testid="fin-drop-overlay"
                    className="absolute inset-0 z-10 flex items-center justify-center rounded-md border-2 border-dashed border-primary bg-accent p-3"
                >
                    <span className="text-sm font-medium text-primary">
                        Solte os arquivos para anexar
                    </span>
                </div>
            )}

            <div className="flex items-end gap-2">
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept={ACCEPT_ATTRIBUTE}
                    aria-label="Anexar arquivos"
                    className="hidden"
                    data-testid="fin-attachment-input"
                    onChange={handleSelectFiles}
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Anexar arquivos"
                    data-testid="fin-paperclip"
                    disabled={disabled}
                    onClick={() => fileInputRef.current?.click()}
                >
                    <Paperclip className="h-4 w-4" />
                </Button>
                <Textarea
                    data-testid="fin-input"
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Pergunte ao Fin sobre suas finanças…"
                    disabled={disabled}
                    className="min-h-10 max-h-40 flex-1 resize-none"
                />
                <Button
                    data-testid="fin-send"
                    size="icon"
                    onClick={handleSend}
                    disabled={disabled || !canSend}
                    aria-label="Enviar mensagem"
                >
                    <Send className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}
