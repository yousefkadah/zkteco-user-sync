import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

type Tone = 'idle' | 'info' | 'success' | 'warning' | 'danger';

const TONE_CLASSES: Record<Tone, string> = {
    idle: 'bg-idle-soft text-idle',
    info: 'bg-info-soft text-info',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
};

const DOT_CLASSES: Record<Tone, string> = {
    idle: 'bg-idle',
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
};

const STATUS_TONE: Record<string, Tone> = {
    parsed: 'idle',
    pending: 'warning',
    syncing: 'info',
    synced: 'success',
    completed: 'success',
    failed: 'danger',
    skipped: 'idle',
};

interface Props {
    /** A sync/import status key (maps to a tone), used when no explicit tone is given. */
    status?: string;
    /** Override the tone explicitly (e.g. an Admin role pill). */
    tone?: Tone;
    /** Custom label; defaults to a title-cased status. */
    children?: ReactNode;
    className?: string;
}

/** A dot + label pill with dark-aware semantic tones. */
export function StatusBadge({ status, tone, children, className }: Props) {
    const resolved: Tone = tone ?? (status ? STATUS_TONE[status] ?? 'idle' : 'idle');
    const label = children ?? (status ? status.charAt(0).toUpperCase() + status.slice(1) : '—');

    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-1 rounded-sm px-1.5 py-0 text-[11px] font-medium',
                TONE_CLASSES[resolved],
                className,
            )}
        >
            <span
                className={cn(
                    'size-1.5 shrink-0 rounded-full',
                    DOT_CLASSES[resolved],
                    status === 'syncing' && 'animate-pulse',
                )}
            />
            {label}
        </span>
    );
}
