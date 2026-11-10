import { useEffect, useRef } from 'react';
import type { FinRun } from '@/types/fin';

const POLL_INTERVAL_MS = 2000;

export function useFinPolling(
    workspaceUuid: string,
    runId: string | null,
    onCompleted: (run: FinRun) => void,
    onFailed: (run: FinRun) => void,
): void {
    const onCompletedRef = useRef(onCompleted);
    const onFailedRef = useRef(onFailed);

    useEffect(() => {
        onCompletedRef.current = onCompleted;
        onFailedRef.current = onFailed;
    });

    useEffect(() => {
        if (!runId) {
            return;
        }

        const currentRunId = runId;

        const intervalId = setInterval(async () => {
            try {
                const response = await fetch(
                    route('fin.runs.show', {
                        workspace: workspaceUuid,
                        run: currentRunId,
                    }),
                );

                if (!response.ok) {
                    return;
                }

                const run = (await response.json()) as FinRun;

                if (run.status === 'completed') {
                    clearInterval(intervalId);
                    onCompletedRef.current(run);
                } else if (run.status === 'failed') {
                    clearInterval(intervalId);
                    onFailedRef.current(run);
                }
            } catch {
                // Silently ignore — polling retries on the next tick.
            }
        }, POLL_INTERVAL_MS);

        return () => clearInterval(intervalId);
    }, [runId, workspaceUuid]);
}
