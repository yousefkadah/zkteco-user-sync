export function ConnectingDots() {
    return (
        <span className="inline-flex items-center gap-0.5">
            <span className="connect-dot" />
            <span className="connect-dot" style={{ animationDelay: '0.15s' }} />
            <span className="connect-dot" style={{ animationDelay: '0.3s' }} />
        </span>
    );
}
