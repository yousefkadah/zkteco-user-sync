import { type ReactNode } from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import AppLayout from '@/layouts/app-layout';
import type { SharedPageProps } from '@/types';

/** Follow the OS colour scheme, like a native app — stamped before first paint. */
const themeQuery = window.matchMedia('(prefers-color-scheme: dark)');
const applyTheme = () => document.documentElement.classList.toggle('dark', themeQuery.matches);
applyTheme();
themeQuery.addEventListener('change', applyTheme);

createInertiaApp({
    title: (title) => (title ? `${title} · ZKTeco User Sync` : 'ZKTeco User Sync'),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
        const page = pages[`./pages/${name}.tsx`] as { default: { layout?: unknown } };

        if (!page) {
            throw new Error(`Inertia page not found: ./pages/${name}.tsx`);
        }

        page.default.layout =
            page.default.layout ?? ((content: ReactNode) => <AppLayout>{content}</AppLayout>);

        return page;
    },
    setup({ el, App, props }) {
        // Only macOS reserves the traffic-light gutter; everything else starts at 12px.
        const platform = (props.initialPage.props as unknown as SharedPageProps).app?.platform;
        const root = document.documentElement;
        root.dataset.platform = platform ?? '';
        if (platform && platform !== 'darwin') {
            root.style.setProperty('--titlebar-inset-start', '12px');
        }

        createRoot(el).render(<App {...props} />);

        // Fade out the boot splash once React has painted.
        requestAnimationFrame(() =>
            requestAnimationFrame(() => {
                const splash = document.getElementById('app-splash');
                if (splash) {
                    splash.style.opacity = '0';
                    window.setTimeout(() => splash.remove(), 400);
                }
            }),
        );
    },
    // No web-style top progress bar inside a fixed window — load feedback lives in the status bar.
    progress: false,
});
