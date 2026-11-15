export type UserType = 'admin' | 'customer';

export interface AdminUser {
    uuid: string;
    name: string;
    email: string;
    type: UserType;
    email_verified_at: string | null;
    created_at: string;
    workspaces_count?: number;
}

export interface AdminWorkspace {
    uuid: string;
    name: string;
    created_at: string;
    members_count?: number;
    role?: string;
}

export interface AdminWorkspaceRef {
    uuid: string;
    name: string;
    role: string;
}

export interface AdminMember {
    user: {
        uuid: string;
        name: string;
        email: string;
    };
    role: string;
    joined_at: string;
}
