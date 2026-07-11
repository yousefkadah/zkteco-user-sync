import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Check, ChevronRight, Cpu, MonitorSmartphone, Send } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AnimatedNumber } from '@/components/animated-number';
import { DataStream } from '@/components/data-stream';
import { Page, Toolbar } from '@/components/shell/page';
import { usePageStatus } from '@/components/shell/shell-status';
import { cn } from '@/lib/utils';
import type { BatchSummary, DeviceLite } from '@/types';

interface TransferUser {
    id: number;
    user_id: string;
    name: string;
    sync_status: string;
    sync_error: string | null;
}

interface Props {
    batch: BatchSummary;
    users: TransferUser[];
    devices: DeviceLite[];
}

export default function ImportTransfer({ batch, users, devices }: Props) {
    const [deviceId, setDeviceId] = useState<string>(String(batch.device?.id ?? devices[0]?.id ?? ''));
    const timer = useRef<number | null>(null);

    const isSyncing = batch.status === 'syncing';
    const isComplete = batch.status === 'completed';

    const arrived = users.filter((user) => user.sync_status === 'synced');
    const toSend = users.filter((user) => user.sync_status !== 'synced');
    const total = users.length;
    const percent = total ? Math.round((arrived.length / total) * 100) : 0;

    const selectedDevice = devices.find((device) => String(device.id) === deviceId);
    const canSend = Boolean(deviceId) && total > 0 && !isSyncing;

    usePageStatus(
        () => ({
            sync: isSyncing ? (
                <span className="flex items-center gap-2">
                    <DataStream direction="to-device" active variant="inline" className="w-16" />
                    Sending…
                </span>
            ) : undefined,
            counts: (
                <span className="tabular-nums">
                    {arrived.length}/{total} synced · {percent}%
                    {batch.failed_count > 0 && <span className="text-danger"> · {batch.failed_count} failed</span>}
                </span>
            ),
        }),
        [isSyncing, arrived.length, total, percent, batch.failed_count],
    );

    useEffect(() => {
        if (isSyncing) {
            timer.current = window.setInterval(() => {
                router.reload({ only: ['batch', 'users'], preserveScroll: true, preserveState: true });
            }, 1500);
        }

        return () => {
            if (timer.current) {
                clearInterval(timer.current);
                timer.current = null;
            }
        };
    }, [isSyncing]);

    const send = () => {
        if (!canSend) {
            return;
        }

        router.post(`/import/${batch.id}/sync`, { device_id: Number(deviceId) }, { preserveScroll: true, preserveState: true });
    };

    const radius = 40;
    const circumference = 2 * Math.PI * radius;
    const dashOffset = circumference * (1 - percent / 100);

    const buttonLabel = isSyncing
        ? 'Sending…'
        : arrived.length === total && total > 0
          ? 'Send again'
          : `Send ${toSend.length} ${toSend.length === 1 ? 'user' : 'users'}`;

    return (
        <Page>
            <Head title={`Send · ${batch.original_filename}`} />

            <Toolbar>
                <div className="flex min-w-0 items-center gap-1.5 text-[13px]">
                    <Link href="/import" className="text-muted-foreground hover:text-foreground">
                        Imports
                    </Link>
                    <ChevronRight className="size-3.5 shrink-0 text-muted-foreground" />
                    <Link href={`/import/${batch.id}`} className="max-w-[240px] truncate text-muted-foreground hover:text-foreground">
                        {batch.original_filename}
                    </Link>
                    <ChevronRight className="size-3.5 shrink-0 text-muted-foreground" />
                    <span className="font-semibold">Send</span>
                </div>

                <Button className="ms-auto" disabled={!canSend} onClick={send}>
                    <Send className={cn('size-4', isSyncing && 'animate-pulse')} />
                    {buttonLabel}
                </Button>
            </Toolbar>

            <div className="min-h-0 flex-1 p-3">
                <div className="grid h-full grid-cols-[1fr_4rem_1fr] gap-3">
                    {/* From the import */}
                    <div className="flex min-h-0 flex-col rounded-md border border-border bg-card">
                        <div className="flex items-center justify-between border-b border-border bg-panel-header px-3 py-2">
                            <div className="flex items-center gap-2">
                                <MonitorSmartphone className="size-4 text-muted-foreground" />
                                <span className="text-[13px] font-semibold">From this import</span>
                            </div>
                            <span className="text-[11px] text-muted-foreground">
                                <AnimatedNumber value={toSend.length} /> to send
                            </span>
                        </div>
                        <div className="flex min-h-0 flex-1 flex-wrap content-start gap-1.5 overflow-y-auto p-3">
                            {toSend.length === 0 ? (
                                <p className="w-full py-6 text-center text-[13px] text-muted-foreground">All users have been sent.</p>
                            ) : (
                                toSend.map((user) => (
                                    <span
                                        key={user.id}
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-sm border border-border bg-background px-2 py-0.5 text-[12px]',
                                            isSyncing && 'animate-pulse',
                                            user.sync_status === 'failed' && 'border-danger/40 bg-danger-soft text-danger',
                                        )}
                                    >
                                        <span className="size-1.5 rounded-full bg-accent-brand/60" />
                                        <span className="mono max-w-[130px] truncate">{user.name || user.user_id}</span>
                                    </span>
                                ))
                            )}
                        </div>
                    </div>

                    {/* Flow connector */}
                    <div className="flex flex-col items-center justify-center gap-2">
                        <DataStream direction="to-device" active={isSyncing} className="min-w-8" />
                        <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                    </div>

                    {/* To the device */}
                    <div className="flex min-h-0 flex-col rounded-md border border-border bg-card">
                        <div className="flex items-center justify-between border-b border-border bg-panel-header px-3 py-2">
                            <div className="flex items-center gap-2">
                                <Cpu className="size-4 text-muted-foreground" />
                                <span className="text-[13px] font-semibold">To device</span>
                            </div>
                            {selectedDevice && (
                                <span className={cn('inline-flex items-center gap-1 text-[11px]', selectedDevice.last_connection_ok ? 'text-success' : 'text-muted-foreground')}>
                                    <span className={cn('size-1.5 rounded-full', selectedDevice.last_connection_ok ? 'bg-success' : 'bg-idle')} />
                                    {selectedDevice.last_connection_ok ? 'Online' : 'Unknown'}
                                </span>
                            )}
                        </div>

                        <div className="flex min-h-0 flex-1 flex-col p-3">
                            {devices.length === 0 ? (
                                <p className="py-6 text-center text-[13px] text-warning">
                                    No devices yet.{' '}
                                    <Link href="/devices" className="underline">
                                        Add or scan for a device
                                    </Link>
                                    .
                                </p>
                            ) : (
                                <>
                                    <Select value={deviceId} onValueChange={setDeviceId} disabled={isSyncing}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose a device" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {devices.map((device) => (
                                                <SelectItem key={device.id} value={String(device.id)}>
                                                    {device.name} ({device.ip_address})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <div className="my-3 flex justify-center">
                                        <div className="relative flex size-24 items-center justify-center">
                                            <svg className="absolute inset-0 -rotate-90" viewBox="0 0 100 100">
                                                <circle cx="50" cy="50" r={radius} fill="none" stroke="currentColor" strokeWidth="6" className="text-border" />
                                                <circle
                                                    cx="50"
                                                    cy="50"
                                                    r={radius}
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="6"
                                                    strokeLinecap="round"
                                                    className={cn('transition-[stroke-dashoffset] duration-500', batch.failed_count > 0 ? 'text-warning' : 'text-success')}
                                                    strokeDasharray={circumference}
                                                    strokeDashoffset={dashOffset}
                                                />
                                            </svg>
                                            {isComplete && batch.failed_count === 0 ? (
                                                <span className="relative flex items-center justify-center">
                                                    <span className="absolute inline-flex size-14 animate-ping rounded-full bg-success/20" />
                                                    <Check className="relative size-8 text-success" strokeWidth={3} />
                                                </span>
                                            ) : (
                                                <div className="text-center">
                                                    <p className="text-xl font-semibold tabular-nums">
                                                        <AnimatedNumber value={arrived.length} />
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground">of {total}</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex min-h-0 flex-1 flex-wrap content-start gap-1.5 overflow-y-auto">
                                        {arrived.map((user) => (
                                            <span
                                                key={user.id}
                                                className="inline-flex animate-in fade-in-0 slide-in-from-bottom-1 items-center gap-1.5 rounded-sm border border-success/30 bg-success-soft px-2 py-0.5 text-[12px] text-success duration-500"
                                            >
                                                <Check className="size-3" />
                                                <span className="mono max-w-[130px] truncate">{user.name || user.user_id}</span>
                                            </span>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </Page>
    );
}
