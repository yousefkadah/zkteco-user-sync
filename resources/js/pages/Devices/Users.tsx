import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RefreshCw } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

interface DeviceUser {
    uid: number;
    user_id: string;
    name: string;
    role: number;
    role_label: string;
    password: string;
    card_no: string | null;
}

interface Props {
    device: { id: number; name: string; ip_address: string; port: number };
    result: { ok: boolean; error?: string; users: DeviceUser[]; count: number };
}

export default function DevicesUsers({ device, result }: Props) {
    const [refreshing, setRefreshing] = useState(false);

    const refresh = () => {
        router.reload({
            onStart: () => setRefreshing(true),
            onFinish: () => setRefreshing(false),
        });
    };

    return (
        <>
            <Head title={`Users on ${device.name}`} />

            <div className="mb-6">
                <Link href="/devices" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="size-4" /> Devices
                </Link>
                <div className="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{device.name}</h1>
                        <p className="mt-1 font-mono text-sm text-muted-foreground">
                            {device.ip_address}:{device.port}
                        </p>
                    </div>
                    <Button variant="outline" onClick={refresh} disabled={refreshing} className="w-full sm:w-auto">
                        <RefreshCw className={cn('size-4', refreshing && 'animate-spin')} />
                        {refreshing ? 'Reading…' : 'Refresh'}
                    </Button>
                </div>
            </div>

            {!result.ok ? (
                <Card className="p-8 text-center">
                    <p className="text-sm font-medium text-destructive">{result.error ?? 'Could not read the device.'}</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Make sure the terminal is powered on and on this network, then Refresh.
                    </p>
                </Card>
            ) : result.users.length === 0 ? (
                <Card className="p-12 text-center text-sm text-muted-foreground">
                    No users are stored on this device yet.
                </Card>
            ) : (
                <>
                    <p className="mb-3 text-sm text-muted-foreground">
                        {result.count} user{result.count === 1 ? '' : 's'} currently on the device
                    </p>
                    <Card className="overflow-hidden">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Slot</TableHead>
                                    <TableHead>User ID</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>PIN</TableHead>
                                    <TableHead>Card</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {result.users.map((user) => (
                                    <TableRow key={user.uid}>
                                        <TableCell className="text-muted-foreground">{user.uid}</TableCell>
                                        <TableCell className="font-mono">{user.user_id || '—'}</TableCell>
                                        <TableCell>{user.name || '—'}</TableCell>
                                        <TableCell>
                                            {user.role === 14 ? (
                                                <span className="rounded bg-violet-100 px-1.5 py-0.5 text-xs font-medium text-violet-700">
                                                    Admin
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">{user.role_label}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-mono text-muted-foreground">{user.password || '—'}</TableCell>
                                        <TableCell className="font-mono text-muted-foreground">{user.card_no ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                </>
            )}
        </>
    );
}
