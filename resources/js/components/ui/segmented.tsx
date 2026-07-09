import { cn } from '@/lib/utils';

export interface SegmentedOption<T extends string> {
    label: string;
    value: T;
}

interface Props<T extends string> {
    options: SegmentedOption<T>[];
    value: T;
    onChange: (value: T) => void;
    className?: string;
}

/** A native-style segmented control — the one place a 1px raised shadow is correct. */
export function Segmented<T extends string>({ options, value, onChange, className }: Props<T>) {
    return (
        <div
            role="tablist"
            className={cn(
                'inline-flex h-[var(--ctl-h)] items-center rounded-md border border-border bg-muted p-0.5 text-[12px]',
                className,
            )}
        >
            {options.map((option) => {
                const selected = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'h-full rounded-[calc(var(--radius-sm)-1px)] px-2.5 transition-colors',
                            selected
                                ? 'bg-card font-medium text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
