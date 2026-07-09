import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

/**
 * Ambient app state (device connection, live sync, counts) docks in the window
 * status bar. Pages push their slots via `usePageStatus`; the StatusBar reads them.
 */
export interface StatusSlots {
    connection?: ReactNode;
    sync?: ReactNode;
    counts?: ReactNode;
}

interface ShellStatusContextValue {
    slots: StatusSlots;
    setSlots: (partial: StatusSlots) => void;
    clearSlots: () => void;
}

const ShellStatusContext = createContext<ShellStatusContextValue | null>(null);

export function ShellStatusProvider({ children }: { children: ReactNode }) {
    const [slots, setSlotsState] = useState<StatusSlots>({});

    const setSlots = useCallback((partial: StatusSlots) => {
        setSlotsState((current) => ({ ...current, ...partial }));
    }, []);
    const clearSlots = useCallback(() => setSlotsState({}), []);

    const value = useMemo(() => ({ slots, setSlots, clearSlots }), [slots, setSlots, clearSlots]);

    return <ShellStatusContext.Provider value={value}>{children}</ShellStatusContext.Provider>;
}

export function useShellStatus() {
    return useContext(ShellStatusContext);
}

/**
 * Publish this page's status-bar slots. `build` is re-run whenever `deps` change,
 * and the slots are cleared when the page unmounts. Keep `deps` to primitive
 * values (counts, statuses) — not freshly-built nodes.
 */
export function usePageStatus(build: () => StatusSlots, deps: unknown[]) {
    const ctx = useShellStatus();

    useEffect(() => {
        ctx?.setSlots(build());
        return () => ctx?.clearSlots();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);
}
