export function ScanRadar() {
    return (
        <div className="flex items-center gap-3 py-1">
            <div className="relative size-11 shrink-0">
                <span className="ping-ring" />
                <span className="ping-ring" style={{ animationDelay: '0.8s' }} />
                <div className="absolute inset-0 overflow-hidden rounded-full border border-primary/30">
                    <div className="radar-sweep absolute inset-0" />
                </div>
                <div className="absolute left-1/2 top-1/2 size-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary" />
            </div>
            <div>
                <p className="text-sm font-medium">Scanning your network…</p>
                <p className="text-xs text-muted-foreground">Probing UDP port 4370 across the subnet</p>
            </div>
        </div>
    );
}
