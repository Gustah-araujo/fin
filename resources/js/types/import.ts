export interface ImportPreviewItem {
    id: string;
    description: string;
    value: number;
    date: string;
    type: string; // "debit" | "credit"
    category_name: string;
    category_uuid: string | null;
    tags: string[]; // array of UUIDs
    tag_names: string[]; // array of names for display
    is_duplicate: boolean;
    confirm_duplicate: boolean;
}

export interface CategoryOption {
    uuid: string;
    name: string;
    type: string; // "expense" | "income" | "both"
    color: string;
    icon: string | null;
}

export interface TagOption {
    uuid: string;
    name: string;
    color: string;
}

export interface ImportPageProps {
    categories?: CategoryOption[];
    tags?: TagOption[];
    preview?: ImportPreviewItem[];
    flash?: {
        success?: string;
        error?: string;
    };
}
