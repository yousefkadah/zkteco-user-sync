import { useShellStatus } from '@/components/shell/shell-status';

function Sep() {
    return <span className="h-3 w-px bg-border-strong" aria-hidden />;
}

/** The pinned window status bar — the home for ambient state + app version. */
export function StatusBar({ version }: { version: string }) {
    const ctx = useShellStatus();
    const slots = ctx?.slots ?? {};

    return (
        <footer className="flex h-[var(--statusbar-h)] select-none items-center gap-2.5 border-t border-border bg-statusbar px-3 text-[11px] text-statusbar-foreground">
            {slots.connection ?? <span className="opacity-80">Ready</span>}
            {slots.sync && (
                <>
                    <Sep />
                    {slots.sync}
                </>
            )}
            <span className="ms-auto flex items-center gap-2.5">
                {slots.counts}
                {slots.counts && <Sep />}
                <span className="tabular-nums opacity-70">v{version}</span>
            </span>
        </footer>
    );
}
