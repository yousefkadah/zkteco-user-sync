import { type ReactNode, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Monitor, Upload } from 'lucide-react';
import { toast } from 'sonner';

import { Toaster } from '@/components/ui/sonner';
import { cn } from '@/lib/utils';
import type { SharedPageProps } from '@/types';

const NAVIGATION = [
    { label: 'Imports', href: '/', icon: Upload, isActive: (path: string) => path === '/' || path.startsWith('/import') },
    { label: 'Devices', href: '/devices', icon: Monitor, isActive: (path: string) => path.startsWith('/devices') },
];

export default function AppLayout({ children }: { children: ReactNode }) {
    const page = usePage<SharedPageProps>();
    const currentPath = page.url.split('?')[0];
    const flash = page.props.flash;
    const version = page.props.app?.version ?? '';

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        } else if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    const renderNav = (labelClass: string) =>
        NAVIGATION.map((item) => {
            const active = item.isActive(currentPath);
            const Icon = item.icon;

            return (
                <Link
                    key={item.href}
                    href={item.href}
                    className={cn(
                        'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                        active
                            ? 'bg-secondary text-secondary-foreground'
                            : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground',
                    )}
                >
                    <Icon className="size-4 shrink-0" />
                    <span className={labelClass}>{item.label}</span>
                </Link>
            );
        });

    return (
        <div className="flex min-h-screen flex-col lg:flex-row">
            {/* Sidebar — large screens */}
            <aside className="hidden border-r bg-card px-4 py-6 lg:flex lg:w-60 lg:shrink-0 lg:flex-col">
                <div className="flex items-center gap-2 px-2">
                    <div className="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                        <Monitor className="size-5" />
                    </div>
                    <div className="leading-tight">
                        <p className="text-sm font-semibold">ZKTeco</p>
                        <p className="text-xs text-muted-foreground">User Sync</p>
                    </div>
                </div>

                <nav className="mt-8 flex flex-col gap-1">{renderNav('')}</nav>

                <div className="mt-auto px-2 text-xs text-muted-foreground">v{version}</div>
            </aside>

            {/* Top bar — small / medium screens */}
            <header className="flex items-center justify-between gap-3 border-b bg-card px-4 py-3 lg:hidden">
                <div className="flex items-center gap-2">
                    <div className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <Monitor className="size-4" />
                    </div>
                    <span className="text-sm font-semibold">ZKTeco User Sync</span>
                </div>
                <nav className="flex items-center gap-1">{renderNav('hidden sm:inline')}</nav>
            </header>

            <div className="flex min-w-0 flex-1 flex-col">
                <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 sm:py-8">{children}</main>
            </div>

            <Toaster position="bottom-right" richColors />
        </div>
    );
}
