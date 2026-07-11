import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AppLogo } from '@/components/app-logo';
import { FullnessMark } from '@/components/fullness-mark';
import type { SharedPageProps } from '@/types';

/**
 * The "by Fullness" lockup pinned to the sidebar footer, plus an About dialog
 * (app version, the Fullness wordmark, and a manual "Check for updates" action).
 */
export function AboutFullness() {
    const [open, setOpen] = useState(false);
    const [checking, setChecking] = useState(false);
    const version = usePage<SharedPageProps>().props.app?.version ?? '';

    const checkForUpdates = () => {
        setChecking(true);
        router.post(
            '/updates/check',
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setChecking(false),
                onSuccess: () =>
                    toast.success('Checking for updates…', {
                        description: 'The app updates itself from GitHub — you’ll be notified when a new version is ready.',
                    }),
                onError: () => toast.error('Could not check for updates.'),
            },
        );
    };

    return (
        <>
            <div className="mt-auto border-t border-border px-2 pt-2">
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="no-drag flex w-full items-center gap-1.5 rounded-md px-1 py-1 text-[11px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    title="About ZKTeco User Sync"
                >
                    <span className="opacity-70">by</span>
                    <FullnessMark className="size-3 shrink-0" />
                    <span className="font-semibold tracking-tight">Fullness</span>
                </button>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>About</DialogTitle>
                    </DialogHeader>

                    <div className="flex flex-col items-center gap-3 py-1 text-center">
                        <AppLogo className="size-14" />
                        <div>
                            <p className="text-[15px] font-semibold">ZKTeco User Sync</p>
                            <p className="text-[12px] tabular-nums text-muted-foreground">Version {version}</p>
                        </div>

                        <Button variant="outline" size="sm" onClick={checkForUpdates} disabled={checking}>
                            {checking ? 'Checking…' : 'Check for updates'}
                        </Button>

                        <div className="mt-1 flex w-full flex-col items-center gap-1 border-t border-border pt-3">
                            <div className="flex items-center gap-1.5">
                                <FullnessMark className="size-4" />
                                <span className="text-[15px] font-semibold tracking-tight">Fullness</span>
                            </div>
                            <p className="text-[11px] text-muted-foreground">A Fullness product · fullness.co.il</p>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
