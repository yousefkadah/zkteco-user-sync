import { cn } from '@/lib/utils';

interface Props {
    direction?: 'to-device' | 'to-app';
    active?: boolean;
    className?: string;
}

/**
 * A horizontal track with dots streaming toward the device (right) or back to
 * the app (left) — the shared visual for any app ⇄ device transfer.
 */
export function DataStream({ direction = 'to-device', active = true, className }: Props) {
    return (
        <div className={cn('relative h-2 w-full overflow-hidden rounded-full bg-border', className)}>
            {active &&
                [0, 1, 2, 3, 4].map((i) => (
                    <span
                        key={i}
                        className={cn('flow-dot', direction === 'to-app' ? 'flow-dot--to-app' : 'flow-dot--to-device')}
                        style={{ animationDelay: `${i * 0.32}s` }}
                    />
                ))}
        </div>
    );
}
