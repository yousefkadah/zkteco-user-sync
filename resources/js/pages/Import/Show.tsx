import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RefreshCw } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StatusBadge } from '@/components/status-badge';
import { cn } from '@/lib/utils';
import type { BatchSummary, DeviceLite, ImportedUserRow, Paginated } from '@/types';

interface Props {
    batch: BatchSummary;
    users: Paginated<ImportedUserRow>;
    devices: DeviceLite[];
}

export default function ImportShow({ batch, users, devices }: Props) {
    const [deviceId, setDeviceId] = useState<string>(
        String(batch.device?.id ?? devices[0]?.id ?? ''),
    );
    const timer = useRef<number | null>(null);

    const isSyncing = batch.status === 'syncing';
    const processed = batch.synced_count + batch.failed_count;
    const percent = batch.valid_rows ? Math.min(100, Math.round((processed / batch.valid_rows) * 100)) : 0;
    const canSync = devices.length > 0 && batch.valid_rows > 0 && !isSyncing;

    useEffect(() => {
        if (isSyncing) {
            timer.current = window.setInterval(() => {
                router.reload({ only: ['batch', 'users'], preserveScroll: true, preserveState: true });
            }, 2000);
        }

        return () => {
            if (timer.current) {
                clearInterval(timer.current);
                timer.current = null;
            }
        };
    }, [isSyncing]);

    const sync = () => {
        if (!deviceId || !canSync) {
            return;
        }

        router.post(
            `/import/${batch.id}/sync`,
            { device_id: Number(deviceId) },
            { preserveScroll: true, preserveState: true },
        );
    };

    const rowStatus = (user: ImportedUserRow) => (!user.is_valid ? 'skipped' : user.sync_status);

    const summary = [
        { label: 'Total', value: batch.total_rows, className: '' },
        { label: 'Valid', value: batch.valid_rows, className: 'text-emerald-600' },
        { label: 'Invalid', value: batch.invalid_rows, className: 'text-rose-500' },
    ];

    return (
        <>
            <Head title={batch.original_filename} />

            <div className="mb-6">
                <Link href="/" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="size-4" /> Imports
                </Link>
                <div className="mt-2 flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{batch.original_filename}</h1>
                    <StatusBadge status={batch.status} />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                {summary.map((card) => (
                    <Card key={card.label} className="p-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{card.label}</p>
                        <p className={cn('mt-1 text-2xl font-semibold', card.className)}>{card.value}</p>
                    </Card>
                ))}
                <Card className="p-4">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Synced</p>
                    <p className="mt-1 text-2xl font-semibold text-blue-600">
                        {batch.synced_count}
                        {batch.failed_count > 0 && (
                            <span className="text-base font-medium text-rose-500"> · {batch.failed_count} failed</span>
                        )}
                    </p>
                </Card>
            </div>

            <Card className="mt-6 p-6">
                <div className="flex flex-wrap items-end gap-4">
                    <div className="flex-1">
                        <p className="mb-1 text-sm font-medium">Target device</p>
                        <Select value={deviceId} onValueChange={setDeviceId} disabled={isSyncing || devices.length === 0}>
                            <SelectTrigger className="max-w-sm">
                                <SelectValue placeholder="Select a device" />
                            </SelectTrigger>
                            <SelectContent>
                                {devices.map((device) => (
                                    <SelectItem key={device.id} value={String(device.id)}>
                                        {device.name} ({device.ip_address})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {devices.length === 0 && (
                            <p className="mt-1 text-xs text-amber-600">
                                <Link href="/devices" className="underline">
                                    Add a device
                                </Link>{' '}
                                first.
                            </p>
                        )}
                    </div>
                    <Button onClick={sync} disabled={!canSync || !deviceId} size="lg" className="w-full sm:w-auto">
                        <RefreshCw className={cn('size-4', isSyncing && 'animate-spin')} />
                        {isSyncing ? 'Syncing…' : `Sync ${batch.valid_rows} users`}
                    </Button>
                </div>

                {(isSyncing || batch.status === 'completed' || batch.status === 'failed') && (
                    <div className="mt-5">
                        <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                            <span>
                                {processed} of {batch.valid_rows} processed
                            </span>
                            <span>{percent}%</span>
                        </div>
                        <Progress
                            value={percent}
                            indicatorClassName={batch.failed_count > 0 && !isSyncing ? 'bg-amber-500' : undefined}
                        />
                    </div>
                )}
            </Card>

            <Card className="mt-6 overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Row</TableHead>
                            <TableHead>User ID</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>On device</TableHead>
                            <TableHead>PIN</TableHead>
                            <TableHead>Card</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.data.map((user) => (
                            <TableRow key={user.id} className={cn(!user.is_valid && 'bg-rose-50/50')}>
                                <TableCell className="text-muted-foreground">{user.row_number}</TableCell>
                                <TableCell className="font-mono">{user.user_id || '—'}</TableCell>
                                <TableCell>{user.name}</TableCell>
                                <TableCell className="font-mono text-muted-foreground">{user.name_ascii || '—'}</TableCell>
                                <TableCell className="font-mono text-muted-foreground">{user.password || '—'}</TableCell>
                                <TableCell className="font-mono text-muted-foreground">{user.card_number || '—'}</TableCell>
                                <TableCell>
                                    {user.privilege === 'admin' ? (
                                        <span className="rounded bg-violet-100 px-1.5 py-0.5 text-xs font-medium text-violet-700">
                                            Admin
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">User</span>
                                    )}
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-col gap-1">
                                        <StatusBadge status={rowStatus(user)} />
                                        {!user.is_valid && user.validation_errors.length > 0 ? (
                                            <span className="text-xs text-rose-500">
                                                {user.validation_errors.join(', ')}
                                            </span>
                                        ) : (
                                            user.sync_error && <span className="text-xs text-rose-500">{user.sync_error}</span>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>

                {users.last_page > 1 && (
                    <div className="flex flex-wrap items-center gap-1 border-t px-4 py-3">
                        {users.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    preserveScroll
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm',
                                        link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-secondary',
                                    )}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={index}
                                    className="px-3 py-1.5 text-sm text-muted-foreground/40"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </div>
                )}
            </Card>
        </>
    );
}
