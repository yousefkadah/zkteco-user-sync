import { type ReactNode, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';

import { Toaster } from '@/components/ui/sonner';
import { Titlebar } from '@/components/shell/titlebar';
import { SidebarRail } from '@/components/shell/sidebar-rail';
import { StatusBar } from '@/components/shell/status-bar';
import { CommandPalette } from '@/components/shell/command-palette';
import { ShellStatusProvider } from '@/components/shell/shell-status';
import { NAVIGATION } from '@/components/shell/nav';
import type { SharedPageProps } from '@/types';

export default function AppLayout({ children }: { children: ReactNode }) {
    const page = usePage<SharedPageProps>();
    const flash = page.props.flash;
    const version = page.props.app?.version ?? '';
    const isMac = page.props.app?.platform === 'darwin';

    const [paletteOpen, setPaletteOpen] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        } else if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    // ⌘K / Ctrl+K — but never hijack typing inside a field.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            const editing =
                target?.tagName === 'INPUT' ||
                target?.tagName === 'TEXTAREA' ||
                target?.isContentEditable;

            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k' && !editing) {
                event.preventDefault();
                setPaletteOpen((open) => !open);
            }
        };

        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <ShellStatusProvider>
            <div className="grid h-screen grid-rows-[var(--titlebar-h)_1fr_var(--statusbar-h)] overflow-hidden bg-window text-foreground">
                <Titlebar isMac={isMac} onOpenPalette={() => setPaletteOpen(true)} />

                <div className="grid min-h-0 grid-cols-[var(--sidebar-w)_1fr] overflow-hidden">
                    <SidebarRail nav={NAVIGATION} />
                    <main className="min-h-0 overflow-hidden bg-background">{children}</main>
                </div>

                <StatusBar version={version} />
            </div>

            <CommandPalette open={paletteOpen} onOpenChange={setPaletteOpen} nav={NAVIGATION} />
            <Toaster position="top-center" richColors closeButton />
        </ShellStatusProvider>
    );
}
