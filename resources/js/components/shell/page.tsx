import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** A page: fixed toolbar + one internal scroll region. The window never scrolls. */
export function Page({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('flex h-full min-h-0 flex-col', className)}>{children}</div>;
}

/** The pinned toolbar strip at the top of a page. Left slot then right slot. */
export function Toolbar({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div
            className={cn(
                'flex h-[var(--toolbar-h)] shrink-0 items-center gap-2 border-b border-border bg-toolbar px-3',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** A thin secondary band below the toolbar (metric strips, sync flow, scan drawer). */
export function SubBar({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div
            className={cn(
                'flex shrink-0 items-center gap-4 border-b border-border bg-panel-header px-3 py-2',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** The single scrollable body of a page. */
export function PageScroll({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('min-h-0 flex-1 overflow-y-auto', className)}>{children}</div>;
}
