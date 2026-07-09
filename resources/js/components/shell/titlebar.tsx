import { Search } from 'lucide-react';

import { AppLogo } from '@/components/app-logo';

/**
 * The window titlebar. On macOS it's the frameless drag band with the native
 * traffic lights inset (gutter reserved via --titlebar-inset-start). On
 * Windows/Linux the native OS frame sits above it and this is a plain header strip.
 */
export function Titlebar({ isMac, onOpenPalette }: { isMac: boolean; onOpenPalette: () => void }) {
    return (
        <header
            className="drag flex h-[var(--titlebar-h)] items-center gap-2 border-b border-border bg-titlebar text-titlebar-foreground"
            style={{ paddingInlineStart: isMac ? 'var(--titlebar-inset-start)' : '12px', paddingInlineEnd: '10px' }}
        >
            <AppLogo className="size-4 shrink-0 opacity-90" />
            <span className="text-[13px] font-semibold tracking-tight">ZKTeco User Sync</span>

            <button
                type="button"
                onClick={onOpenPalette}
                className="no-drag ms-auto flex h-6 items-center gap-1.5 rounded-md border border-border bg-background/50 px-2 text-[11px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
                <Search className="size-3" />
                Search
                <kbd className="font-sans opacity-70">⌘K</kbd>
            </button>
        </header>
    );
}
