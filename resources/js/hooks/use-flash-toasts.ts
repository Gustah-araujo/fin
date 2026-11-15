import { useEffect, useRef } from 'react';
import { usePage, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { dispatchToast } from '@/lib/toast';

export function useFlashToasts(): void {
    const { flash } = usePage().props;
    const consumedFlashRef = useRef<Set<string>>(new Set());

    useEffect(() => {
        if (flash?.success) {
            const key = `success:${flash.success}`;
            if (!consumedFlashRef.current.has(key)) {
                consumedFlashRef.current.add(key);
                toast.success(flash.success);
                dispatchToast('success', flash.success);
            }
        }
        if (flash?.error) {
            const key = `error:${flash.error}`;
            if (!consumedFlashRef.current.has(key)) {
                consumedFlashRef.current.add(key);
                toast.error(flash.error);
                dispatchToast('error', flash.error);
            }
        }
    }, [flash?.success, flash?.error]);

    // Dismiss sonner toasts on SPA navigation — prevents toasts from persisting
    // across Inertia visits (flash messages would otherwise re-show on every
    // navigation since the Toaster component stays mounted).
    useEffect(() => {
        const handleNavigate = (): void => {
            toast.dismiss();
            // Clear consumed flash keys so that a new page visit with the
            // same flash message (e.g. "Conta criada com sucesso.") can
            // dispatch its toast again. Without this, the ref persists
            // across SPA navigations and blocks legitimate re-dispatches.
            consumedFlashRef.current.clear();
        };
        // Inertia's router.on() returns the unsubscribe function.
        const unsubscribe = router.on('before', handleNavigate);
        return () => {
            unsubscribe();
        };
    }, []);
}
