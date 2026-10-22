export interface ToastBufferItem {
    type: string;
    message: string;
    timestamp: number;
}

export function dispatchToast(type: string, message: string): void {
    const detail = { type, message };
    window.dispatchEvent(new CustomEvent('toast', { detail }));

    const w = window as unknown as { __toastBuffer?: ToastBufferItem[] };
    if (!w.__toastBuffer) {
        w.__toastBuffer = [];
    }
    w.__toastBuffer.push({
        ...detail,
        timestamp: Date.now(),
    });
}
