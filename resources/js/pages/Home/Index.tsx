import { Head, Link } from '@inertiajs/react';
import { Monitor, Upload } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { AppLogo } from '@/components/app-logo';
import { FullnessMark } from '@/components/fullness-mark';
import { Page } from '@/components/shell/page';

export default function Home() {
    return (
        <Page>
            <Head title="Home" />

            <div className="flex h-full items-center justify-center overflow-y-auto p-8">
                <div className="flex max-w-xl flex-col items-center text-center">
                    {/* ZKTeco app logo + Fullness logo */}
                    <div className="mb-8 flex items-center justify-center gap-5">
                        <AppLogo className="size-16 shrink-0" />
                        <div className="h-12 w-px bg-border" />
                        <div className="flex items-center gap-2">
                            <FullnessMark className="size-9 shrink-0" />
                            <span className="text-2xl font-bold tracking-tight">Fullness</span>
                        </div>
                    </div>

                    <h1 className="text-[26px] font-bold tracking-tight">ZKTeco User Sync</h1>
                    <p className="mt-3 text-[15px] leading-relaxed text-muted-foreground">
                        Bulk-provision your ZKTeco attendance &amp; access terminals from an Excel file. Upload a list of
                        employees, review and edit the rows, then sync them — with PIN and card — straight to the device
                        over your local network. No cloud, everything stays on your machine.
                    </p>

                    <div className="mt-7 flex flex-wrap items-center justify-center gap-3">
                        <Button size="lg" asChild>
                            <Link href="/import">
                                <Upload className="size-4" /> Import users
                            </Link>
                        </Button>
                        <Button size="lg" variant="outline" asChild>
                            <Link href="/devices">
                                <Monitor className="size-4" /> Manage devices
                            </Link>
                        </Button>
                    </div>

                    <div className="mt-10 flex items-center gap-1.5 text-[12px] text-muted-foreground">
                        <span className="opacity-70">A</span>
                        <FullnessMark className="size-3.5" />
                        <span className="font-semibold">Fullness</span>
                        <span className="opacity-70">product · fullness.co.il</span>
                    </div>
                </div>
            </div>
        </Page>
    );
}
