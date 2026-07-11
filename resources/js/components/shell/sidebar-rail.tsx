import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import { AboutFullness } from '@/components/shell/about-fullness';
import type { NavItem } from '@/components/shell/nav';

/** The primary navigation rail — dense, with a leading active-accent bar (RTL-aware). */
export function SidebarRail({ nav }: { nav: NavItem[] }) {
    const { url } = usePage();
    const path = url.split('?')[0];

    return (
        <nav className="flex min-h-0 flex-col gap-0.5 border-e border-border bg-sidebar px-2 py-3 text-sidebar-foreground">
            {nav.map((item) => {
                const active = item.isActive(path);
                const Icon = item.icon;

                return (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={cn(
                            'no-drag relative flex h-8 items-center gap-2 rounded-md px-2 text-[13px] transition-colors',
                            active
                                ? 'bg-sidebar-active font-medium text-sidebar-active-foreground'
                                : 'hover:bg-accent hover:text-foreground',
                        )}
                    >
                        {active && (
                            <span className="absolute inset-y-1.5 start-0 w-0.5 rounded-full bg-accent-brand" />
                        )}
                        <Icon className="size-4 shrink-0 opacity-80" />
                        {item.label}
                    </Link>
                );
            })}

            <AboutFullness />
        </nav>
    );
}
