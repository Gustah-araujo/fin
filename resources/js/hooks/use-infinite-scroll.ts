import { useEffect, useRef, type RefObject } from 'react';

interface UseInfiniteScrollOptions {
    hasMore: boolean;
    onLoadMore: () => void;
    threshold?: number;
}

export function useInfiniteScroll({
    hasMore,
    onLoadMore,
    threshold = 100,
}: UseInfiniteScrollOptions): RefObject<HTMLDivElement | null> {
    const sentinelRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        const sentinel = sentinelRef.current;
        if (!sentinel) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore) {
                    onLoadMore();
                }
            },
            { rootMargin: `${threshold}px` },
        );

        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [hasMore, onLoadMore, threshold]);

    return sentinelRef;
}
