/** A compact scanning indicator — a small radar sweep + one terse line, sized for a toolbar. */
export function ScanRadar({ label = 'Scanning subnet · UDP 4370' }: { label?: string }) {
    return (
        <span className="inline-flex items-center gap-2 text-[12px] text-muted-foreground">
            <span className="relative size-4 shrink-0">
                <span className="ping-ring" />
                <span className="ping-ring" style={{ animationDelay: '0.8s' }} />
                <span className="absolute inset-0 overflow-hidden rounded-full border border-accent-brand/30">
                    <span className="radar-sweep absolute inset-0 block" />
                </span>
                <span className="absolute left-1/2 top-1/2 size-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-accent-brand" />
            </span>
            {label}
        </span>
    );
}
