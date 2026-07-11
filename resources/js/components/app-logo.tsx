export function AppLogo({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 400 400" className={className} xmlns="http://www.w3.org/2000/svg" role="img" aria-label="ZKTeco User Sync">
            <defs>
                <linearGradient id="app-logo-tile" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#3b82f6" />
                    <stop offset="1" stopColor="#1d4ed8" />
                </linearGradient>
            </defs>
            <rect width="400" height="400" rx="92" fill="url(#app-logo-tile)" />
            <path d="M0 96 A96 96 0 0 1 96 0 H304 A96 96 0 0 1 400 96 V148 Q200 96 0 148 Z" fill="#ffffff" opacity="0.1" />
            <rect x="224" y="96" width="92" height="208" rx="26" fill="#ffffff" />
            <rect x="242" y="118" width="56" height="104" rx="13" fill="#2563eb" opacity="0.16" />
            <circle cx="270" cy="150" r="16" fill="#2563eb" />
            <path d="M248 198 c0 -20 9 -32 22 -32 s22 12 22 32 z" fill="#2563eb" />
            <circle cx="270" cy="266" r="20" fill="none" stroke="#3b82f6" strokeWidth="8" />
            <circle cx="270" cy="266" r="6" fill="#3b82f6" />
            <circle cx="72" cy="200" r="13" fill="#ffffff" opacity="0.5" />
            <circle cx="120" cy="200" r="17" fill="#ffffff" opacity="0.78" />
            <circle cx="176" cy="200" r="22" fill="#ffffff" />
            <path d="M206 200 l26 -18 v36 z" fill="#ffffff" />

            {/* Fullness maker seal — co-brands the app icon */}
            <circle cx="326" cy="330" r="43" fill="#1d4ed8" />
            <circle cx="326" cy="330" r="39" fill="#ffffff" />
            <g transform="translate(297 309) scale(1.35)">
                <path
                    d="M42.84,5.76L40.03,0l-23.3,21.24c-0.83,0.71-0.93,1.96-0.22,2.79s1.96,0.93,2.79,0.22l1.74-1.49l2.84,2.33L42.84,5.76z"
                    fill="#00B8E4"
                />
                <path
                    d="M17.88,20.19L2.67,7.67L0,13.17l18.2,17.67l5.69-5.76l-2.85-2.32l-1.74,1.49c-0.83,0.71-2.08,0.61-2.79-0.22c-0.71-0.83-0.61-2.08,0.22-2.79L17.88,20.19z"
                    fill="#61FFF8"
                />
            </g>
        </svg>
    );
}
