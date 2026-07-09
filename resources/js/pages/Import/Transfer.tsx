import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Cpu, MonitorSmartphone, Send } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AnimatedNumber } from '@/components/animated-number';
import { DataStream } from '@/components/data-stream';
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

    const radius = 46;
    const circumference = 2 * Math.PI * radius;
    const dashOffset = circumference * (1 - percent / 100);

    const buttonLabel = isSyncing
        ? 'Sending…'
        : arrived.length === total && total > 0
          ? 'Send again'
          : `Send ${toSend.length} ${toSend.length === 1 ? 'user' : 'users'}`;

    return (
        <>
            <Head title={`Send · ${batch.original_filename}`} />

            <div className="mb-6">
                <Link href={`/import/${batch.id}`} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="size-4" /> Back to import
                </Link>
                <h1 className="mt-2 text-2xl font-semibold">Send users to a device</h1>
                <p className="text-sm text-muted-foreground">{batch.original_filename}</p>
            </div>

            <div className="grid items-stretch gap-4 lg:grid-cols-[1fr_9rem_1fr]">
                {/* From the import */}
                <Card className="flex flex-col p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <MonitorSmartphone className="size-4 text-muted-foreground" />
                            <h2 className="text-sm font-semibold">From this import</h2>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            <AnimatedNumber value={toSend.length} /> to send
                        </span>
                    </div>
                    <div className="flex max-h-[380px] flex-wrap content-start gap-2 overflow-y-auto">
                        {toSend.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">All users have been sent 🎉</p>
                        ) : (
                            toSend.map((user) => (
                                <span
                                    key={user.id}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-full border bg-background px-2.5 py-1 text-xs',
                                        isSyncing && 'animate-pulse',
                                        user.sync_status === 'failed' && 'border-rose-300 bg-rose-50 text-rose-700',
                                    )}
                                >
                                    <span className="size-1.5 rounded-full bg-primary/60" />
                                    <span className="max-w-[130px] truncate">{user.name || user.user_id}</span>
                                </span>
                            ))
                        )}
                    </div>
                </Card>

                {/* Flow connector */}
                <div className="flex items-center justify-center">
                    <div className="flex w-full items-center gap-2">
                        <DataStream direction="to-device" active={isSyncing} className="min-w-10" />
                        <ArrowRight className="size-5 shrink-0 text-muted-foreground" />
                    </div>
                </div>

                {/* To the device */}
                <Card className="flex flex-col p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Cpu className="size-4 text-muted-foreground" />
                            <h2 className="text-sm font-semibold">To device</h2>
                        </div>
                        {selectedDevice && (
                            <span className={cn('inline-flex items-center gap-1 text-xs', selectedDevice.last_connection_ok ? 'text-emerald-600' : 'text-muted-foreground')}>
                                <span className={cn('size-1.5 rounded-full', selectedDevice.last_connection_ok ? 'bg-emerald-500' : 'bg-muted-foreground')} />
                                {selectedDevice.last_connection_ok ? 'Online' : 'Unknown'}
                            </span>
                        )}
                    </div>

                    {devices.length === 0 ? (
                        <p className="py-6 text-center text-sm text-amber-600">
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

                            <div className="my-4 flex justify-center">
                                <div className="relative flex size-28 items-center justify-center">
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
                                            className={cn('transition-[stroke-dashoffset] duration-500', batch.failed_count > 0 ? 'text-amber-500' : 'text-emerald-500')}
                                            strokeDasharray={circumference}
                                            strokeDashoffset={dashOffset}
                                        />
                                    </svg>
                                    {isComplete && batch.failed_count === 0 ? (
                                        <span className="relative flex items-center justify-center">
                                            <span className="absolute inline-flex size-16 animate-ping rounded-full bg-emerald-500/20" />
                                            <Check className="relative size-9 text-emerald-600" strokeWidth={3} />
                                        </span>
                                    ) : (
                                        <div className="text-center">
                                            <p className="text-2xl font-semibold tabular-nums">
                                                <AnimatedNumber value={arrived.length} />
                                            </p>
                                            <p className="text-[11px] text-muted-foreground">of {total}</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="flex max-h-[180px] flex-wrap content-start gap-2 overflow-y-auto">
                                {arrived.map((user) => (
                                    <span
                                        key={user.id}
                                        className="inline-flex animate-in fade-in-0 slide-in-from-left-6 items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 duration-500"
                                    >
                                        <Check className="size-3" />
                                        <span className="max-w-[130px] truncate">{user.name || user.user_id}</span>
                                    </span>
                                ))}
                            </div>
                        </>
                    )}
                </Card>
            </div>

            <div className="mt-6 flex flex-col items-center gap-3">
                <Button size="lg" className="min-w-64" disabled={!canSend} onClick={send}>
                    <Send className={cn('size-4', isSyncing && 'animate-pulse')} />
                    {buttonLabel}
                    {!isSyncing && arrived.length !== total && <ArrowRight className="size-4" />}
                </Button>

                <div className="w-full max-w-md">
                    <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                        <span>
                            <AnimatedNumber value={arrived.length} /> synced
                            {batch.failed_count > 0 && (
                                <>
                                    {' '}
                                    · <span className="text-rose-500">{batch.failed_count} failed</span>
                                </>
                            )}
                        </span>
                        <span>{percent}%</span>
                    </div>
                    <div className="h-2 w-full overflow-hidden rounded-full bg-secondary">
                        <div
                            className={cn('h-full rounded-full transition-all duration-500', batch.failed_count > 0 ? 'bg-amber-500' : 'bg-primary')}
                            style={{ width: `${percent}%` }}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
