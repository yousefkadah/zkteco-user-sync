import { Check, Cpu, MonitorSmartphone } from 'lucide-react';

import { AnimatedNumber } from '@/components/animated-number';
import { DataStream } from '@/components/data-stream';
import { cn } from '@/lib/utils';

interface Props {
    status: string;
    total: number;
    synced: number;
    failed: number;
    deviceName?: string;
}

function ChipStack({ count }: { count: number }) {
    const shown = Math.min(count, 5);

    return (
        <div className="flex h-5 items-center justify-center">
            {count === 0 ? (
                <span className="text-[11px] text-muted-foreground">all sent</span>
            ) : (
                <>
                    {Array.from({ length: shown }).map((_, i) => (
                        <span
                            key={i}
                            className="-ms-1.5 size-3.5 rounded-[3px] border-2 border-card bg-accent-brand/70 first:ms-0"
                        />
                    ))}
                    {count > 5 && <span className="ms-1 text-[10px] text-muted-foreground">+{count - 5}</span>}
                </>
            )}
        </div>
    );
}

/**
 * Visualises users flowing from the app (left) to the device (right): a shrinking
 * chip stack on the app, an animated stream, and a fill-ring + count-up on the
 * device that lands on a checkmark burst when the transfer completes clean.
 */
export function SyncFlow({ status, total, synced, failed, deviceName }: Props) {
    const processed = synced + failed;
    const remaining = Math.max(0, total - processed);
    const isSyncing = status === 'syncing';
    const isComplete = status === 'completed';
    const percent = total ? Math.min(100, Math.round((processed / total) * 100)) : 0;

    const radius = 32;
    const circumference = 2 * Math.PI * radius;
    const dashOffset = circumference * (1 - (total ? processed / total : 0));

    const pipeLabel = isSyncing
        ? 'sending…'
        : isComplete
          ? 'transfer complete'
          : status === 'failed'
            ? 'transfer failed'
            : 'ready';

    return (
        <div>
            <div className="flex items-stretch gap-3">
                <div className="flex w-28 shrink-0 flex-col items-center justify-center rounded-md border border-border bg-muted/40 p-3 text-center">
                    <div className="flex size-10 items-center justify-center rounded-lg bg-accent-brand text-accent-brand-fg">
                        <MonitorSmartphone className="size-5" />
                    </div>
                    <p className="mt-2 text-xs font-medium">This app</p>
                    <p className="text-lg font-semibold tabular-nums">
                        <AnimatedNumber value={remaining} />
                    </p>
                    <ChipStack count={remaining} />
                </div>

                <div className="flex flex-1 flex-col justify-center">
                    <DataStream direction="to-device" active={isSyncing} />
                    <div className="mt-2 text-center text-[11px] text-muted-foreground">{pipeLabel}</div>
                </div>

                <div className="flex w-28 shrink-0 flex-col items-center justify-center rounded-md border border-border bg-muted/40 p-3 text-center">
                    <div className="relative flex size-16 items-center justify-center">
                        <svg className="absolute inset-0 -rotate-90" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r={radius} fill="none" stroke="currentColor" strokeWidth="6" className="text-border" />
                            <circle
                                cx="40"
                                cy="40"
                                r={radius}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="6"
                                strokeLinecap="round"
                                className={cn('transition-[stroke-dashoffset] duration-500', failed > 0 ? 'text-warning' : 'text-success')}
                                strokeDasharray={circumference}
                                strokeDashoffset={dashOffset}
                            />
                        </svg>
                        {isComplete && failed === 0 ? (
                            <span className="relative flex items-center justify-center">
                                <span className="absolute inline-flex size-10 animate-ping rounded-full bg-success/25" />
                                <Check className="relative size-6 text-success" strokeWidth={3} />
                            </span>
                        ) : (
                            <Cpu className="size-5 text-muted-foreground" />
                        )}
                    </div>
                    <p className="mt-1 max-w-full truncate text-xs font-medium" title={deviceName}>
                        {deviceName ?? 'Device'}
                    </p>
                    <p className="text-[11px] text-muted-foreground">
                        <AnimatedNumber value={synced} /> received
                    </p>
                </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center justify-center gap-x-5 gap-y-1 text-xs">
                <span className="flex items-center gap-1.5">
                    <span className="size-2 rounded-full bg-success" /> <AnimatedNumber value={synced} /> synced
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="size-2 rounded-full bg-danger" /> <AnimatedNumber value={failed} /> failed
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="size-2 rounded-full bg-muted-foreground/40" /> <AnimatedNumber value={remaining} /> pending
                </span>
                <span className="text-muted-foreground">· {percent}%</span>
            </div>
        </div>
    );
}
