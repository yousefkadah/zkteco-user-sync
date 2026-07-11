/**
 * The Fullness brand checkmark, isolated from the marketing wordmark
 * (businesscrm: public/assets/logo/fullness-logo.svg). The two blades keep their
 * brand cyan in both light and dark themes — like the app's own AppLogo keeps its
 * gradient. Pair it with the word "Fullness" typeset in currentColor so the text
 * inherits the surrounding token colour.
 */
export function FullnessMark({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 43 31" className={className} xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Fullness">
            <path
                d="M42.84,5.76L40.03,0l-23.3,21.24c-0.83,0.71-0.93,1.96-0.22,2.79s1.96,0.93,2.79,0.22l1.74-1.49l2.84,2.33L42.84,5.76z"
                fill="#00B8E4"
            />
            <path
                d="M17.88,20.19L2.67,7.67L0,13.17l18.2,17.67l5.69-5.76l-2.85-2.32l-1.74,1.49c-0.83,0.71-2.08,0.61-2.79-0.22c-0.71-0.83-0.61-2.08,0.22-2.79L17.88,20.19z"
                fill="#61FFF8"
            />
        </svg>
    );
}
