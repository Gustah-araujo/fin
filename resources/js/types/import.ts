export interface ImportPreviewItem {
    id: string;
    description: string;
    value: number;
    date: string;
    type: string;
    category_name: string;
    is_duplicate: boolean;
    confirm_duplicate: boolean;
}

export interface ImportPageProps {
    type: 'expense' | 'income';
    preview?: ImportPreviewItem[];
    flash?: {
        success?: string;
        error?: string;
    };
}
