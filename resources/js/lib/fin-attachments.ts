import {
    File,
    FileSpreadsheet,
    FileText,
    Image as ImageIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FinAttachmentKind } from '@/types/fin';

const IMAGE_EXTENSIONS = ['.png', '.jpg', '.jpeg', '.webp'];
const SPREADSHEET_EXTENSIONS = ['.csv', '.xlsx', '.xls'];
const TEXT_EXTENSIONS = ['.pdf', '.txt'];

export function fileExtension(name: string): string {
    const index = name.toLowerCase().lastIndexOf('.');

    return index === -1 ? '' : name.slice(index);
}

export function attachmentKind(name: string): FinAttachmentKind {
    return IMAGE_EXTENSIONS.includes(fileExtension(name))
        ? 'image'
        : 'document';
}

export function attachmentIcon(name: string): LucideIcon {
    const extension = fileExtension(name);

    if (IMAGE_EXTENSIONS.includes(extension)) {
        return ImageIcon;
    }
    if (SPREADSHEET_EXTENSIONS.includes(extension)) {
        return FileSpreadsheet;
    }
    if (TEXT_EXTENSIONS.includes(extension)) {
        return FileText;
    }

    return File;
}
