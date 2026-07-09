import { cn } from '@/lib/utils';

interface Props {
    direction?: 'to-device' | 'to-app';
    active?: boolean;
    variant?: 'default' | 'inline';
    className?: string;
}

/**
 * A horizontal track with dots streaming toward the device (right) or back to
 * the app (left) — the shared visual for any app ⇄ device transfer. The `inline`
 * variant is sized for the status bar / toolbar.
 */
export function DataStream({ direction = 'to-device', active = true, variant = 'default', className }: Props) {
    const inline = variant === 'inline';

    return (
        <div
            className={cn(
                'relative w-full overflow-hidden rounded-full bg-border-strong',
                inline ? 'h-1 flow-track--inline' : 'h-2',
                className,
            )}
        >
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
