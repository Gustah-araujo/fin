export type FinMessageRole = 'user' | 'assistant';
export type FinRunStatus = 'pending' | 'processing' | 'completed' | 'failed';

export type FinAttachmentKind = 'image' | 'document';

export interface FinAttachment {
    name: string;
    kind: FinAttachmentKind;
}

export interface FinMessage {
    id: string;
    role: FinMessageRole;
    content: string;
    attachments?: FinAttachment[];
    created_at: string;
}

export interface FinConversation {
    id: string;
    title: string;
    messages?: FinMessage[];
    created_at: string;
    updated_at: string;
}

export interface FinRun {
    id: string;
    status: FinRunStatus;
    error_message: string | null;
    result_message_id: string | null;
    created_at: string;
}
