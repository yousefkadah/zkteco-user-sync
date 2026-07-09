import { useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { CornerDownLeft, Download, Search } from 'lucide-react';

import { cn } from '@/lib/utils';
import type { NavItem } from '@/components/shell/nav';

interface Command {
    id: string;
    label: string;
    group: string;
    icon?: React.ComponentType<{ className?: string }>;
    run: () => void;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    nav: NavItem[];
}

/** A lightweight ⌘K command palette — navigation + global quick actions. */
export function CommandPalette({ open, onOpenChange, nav }: Props) {
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    const commands: Command[] = useMemo(
        () => [
            ...nav.map((n) => ({
                id: `nav-${n.href}`,
                label: `Go to ${n.label}`,
                group: 'Navigate',
                icon: n.icon,
                run: () => router.visit(n.href),
            })),
            {
                id: 'template',
                label: 'Download user template',
                group: 'Imports',
                icon: Download,
                run: () => {
                    window.location.href = '/template';
                },
            },
        ],
        [nav],
    );

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return commands;
        return commands.filter(
            (c) => c.label.toLowerCase().includes(q) || c.group.toLowerCase().includes(q),
        );
    }, [commands, query]);

    useEffect(() => {
        if (open) {
            setQuery('');
            setActive(0);
            const id = window.setTimeout(() => inputRef.current?.focus(), 0);
            return () => window.clearTimeout(id);
        }
    }, [open]);

    useEffect(() => setActive(0), [query]);

    if (!open) return null;

    const run = (command?: Command) => {
        if (!command) return;
        onOpenChange(false);
        command.run();
    };

    const onKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((i) => Math.min(filtered.length - 1, i + 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((i) => Math.max(0, i - 1));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            run(filtered[active]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            onOpenChange(false);
        }
    };

    let lastGroup = '';

    return createPortal(
        <div
            className="fixed inset-0 z-[100] flex items-start justify-center bg-black/25 pt-[12vh] animate-in fade-in-0 duration-100"
            onMouseDown={() => onOpenChange(false)}
        >
            <div
                className="no-drag w-[560px] max-w-[92vw] overflow-hidden rounded-lg border border-border bg-popover text-popover-foreground shadow-xl"
                onMouseDown={(event) => event.stopPropagation()}
                onKeyDown={onKeyDown}
            >
                <div className="flex h-11 items-center gap-2 border-b border-border px-3">
                    <Search className="size-4 shrink-0 text-muted-foreground" />
                    <input
                        ref={inputRef}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Type a command or search…"
                        className="h-full w-full bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
                    />
                    <kbd className="rounded border border-border px-1 text-[10px] text-muted-foreground">esc</kbd>
                </div>

                <div className="max-h-[320px] overflow-y-auto p-1">
                    {filtered.length === 0 ? (
                        <p className="px-3 py-8 text-center text-[13px] text-muted-foreground">No matches</p>
                    ) : (
                        filtered.map((command, index) => {
                            const showHeader = command.group !== lastGroup;
                            lastGroup = command.group;
                            const Icon = command.icon;

                            return (
                                <div key={command.id}>
                                    {showHeader && (
                                        <p className="px-2 pb-1 pt-2 text-[11px] font-medium text-muted-foreground">
                                            {command.group}
                                        </p>
                                    )}
                                    <button
                                        type="button"
                                        onMouseMove={() => setActive(index)}
                                        onClick={() => run(command)}
                                        className={cn(
                                            'flex h-8 w-full items-center gap-2 rounded-md px-2 text-[13px]',
                                            index === active
                                                ? 'bg-accent text-accent-foreground'
                                                : 'text-foreground',
                                        )}
                                    >
                                        {Icon && <Icon className="size-4 shrink-0 opacity-80" />}
                                        <span className="flex-1 text-start">{command.label}</span>
                                        {index === active && (
                                            <CornerDownLeft className="size-3.5 opacity-50" />
                                        )}
                                    </button>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
