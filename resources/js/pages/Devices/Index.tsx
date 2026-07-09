import { type FormEvent, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Check, Loader2, Pencil, Plus, Trash2, Users, Wifi, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StatusBadge } from '@/components/status-badge';
import { ConnectingDots } from '@/components/connecting-dots';
import { ScanRadar } from '@/components/scan-radar';
import { Page, PageScroll, SubBar, Toolbar } from '@/components/shell/page';
import { usePageStatus } from '@/components/shell/shell-status';
import { cn } from '@/lib/utils';
import type { Device } from '@/types';

interface Props {
    devices: Device[];
}

interface DeviceForm {
    name: string;
    ip_address: string;
    port: number | string;
    comm_key: number | string;
    notes: string;
}

interface DiscoveredDevice {
    ip_address: string;
    serial_number: string | null;
    name: string | null;
    firmware: string | null;
    already_added: boolean;
    suggested_name: string;
}

const EMPTY_FORM: DeviceForm = { name: '', ip_address: '', port: 4370, comm_key: '', notes: '' };

export default function DevicesIndex({ devices }: Props) {
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [testingId, setTestingId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [form, setForm] = useState<DeviceForm>(EMPTY_FORM);
    const [scanning, setScanning] = useState(false);
    const [scanned, setScanned] = useState(false);
    const [scanError, setScanError] = useState<string | null>(null);
    const [discovered, setDiscovered] = useState<DiscoveredDevice[]>([]);

    const onlineCount = devices.filter((device) => device.last_connection_ok).length;

    usePageStatus(
        () => ({
            connection: (
                <span className="flex items-center gap-1.5">
                    <span className={cn('size-1.5 rounded-full', onlineCount > 0 ? 'bg-success' : 'bg-idle')} />
                    {onlineCount} online
                </span>
            ),
            counts: (
                <span className="tabular-nums">
                    {devices.length} device{devices.length === 1 ? '' : 's'}
                </span>
            ),
        }),
        [devices.length, onlineCount],
    );

    const set = <K extends keyof DeviceForm>(key: K, value: DeviceForm[K]) =>
        setForm((current) => ({ ...current, [key]: value }));

    const openCreate = () => {
        setEditingId(null);
        setForm(EMPTY_FORM);
        setErrors({});
        setOpen(true);
    };

    const openEdit = (device: Device) => {
        setEditingId(device.id);
        setForm({
            name: device.name,
            ip_address: device.ip_address,
            port: device.port,
            comm_key: device.comm_key ?? '',
            notes: device.notes ?? '',
        });
        setErrors({});
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (formErrors: Record<string, string>) => setErrors(formErrors),
            onSuccess: () => setOpen(false),
        };

        if (editingId) {
            router.put(`/devices/${editingId}`, form, options);
        } else {
            router.post('/devices', form, options);
        }
    };

    const remove = (device: Device) => {
        if (!window.confirm(`Remove device "${device.name}"?`)) {
            return;
        }

        router.delete(`/devices/${device.id}`, { preserveScroll: true });
    };

    const test = (device: Device) => {
        setTestingId(device.id);
        router.post(`/devices/${device.id}/test`, {}, {
            preserveScroll: true,
            onFinish: () => setTestingId(null),
        });
    };

    const scan = async () => {
        setScanning(true);
        setScanError(null);

        try {
            const response = await fetch('/devices/scan', { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                throw new Error('Scan request failed');
            }

            const data = await response.json();
            setDiscovered(data.devices ?? []);
            setScanned(true);
        } catch {
            setScanError('Network scan failed. Make sure this machine is on the same network as the devices.');
        } finally {
            setScanning(false);
        }
    };

    const addDiscovered = (device: DiscoveredDevice) => {
        router.post(
            '/devices',
            { name: device.suggested_name, ip_address: device.ip_address, port: 4370 },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDiscovered((list) =>
                        list.map((item) =>
                            item.ip_address === device.ip_address ? { ...item, already_added: true } : item,
                        ),
                    );
                },
            },
        );
    };

    const dismissScan = () => {
        setScanned(false);
        setScanError(null);
        setDiscovered([]);
    };

    const formatDate = (iso: string | null) => (iso ? new Date(iso).toLocaleString() : 'Never');

    const showScanStrip = scanning || scanned || Boolean(scanError);

    return (
        <Page>
            <Head title="Devices" />

            <Toolbar>
                <span className="text-[13px] font-semibold">Devices</span>
                <span className="rounded-sm bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">
                    {devices.length}
                </span>

                <div className="ms-auto flex items-center gap-2">
                    <Button variant="outline" size="sm" onClick={scan} disabled={scanning}>
                        {scanning ? <Loader2 className="size-4 animate-spin" /> : <Wifi className="size-4" />}
                        {scanning ? 'Scanning…' : 'Scan network'}
                    </Button>
                    <Button size="sm" onClick={openCreate}>
                        <Plus className="size-4" /> Add device
                    </Button>
                </div>
            </Toolbar>

            {showScanStrip && (
                <SubBar className="flex-col items-stretch gap-2">
                    <div className="flex items-center justify-between">
                        {scanning ? (
                            <ScanRadar />
                        ) : scanError ? (
                            <span className="text-[12px] text-danger">{scanError}</span>
                        ) : discovered.length === 0 ? (
                            <span className="text-[12px] text-muted-foreground">
                                No ZKTeco devices found on this network.
                            </span>
                        ) : (
                            <span className="text-[12px] font-medium">
                                Discovered {discovered.length} device{discovered.length === 1 ? '' : 's'}
                            </span>
                        )}
                        {!scanning && (
                            <button
                                type="button"
                                className="flex size-5 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent hover:text-foreground"
                                onClick={dismissScan}
                            >
                                <X className="size-3.5" />
                            </button>
                        )}
                    </div>

                    {!scanning && discovered.length > 0 && (
                        <div className="flex gap-2 overflow-x-auto pb-1">
                            {discovered.map((device) => (
                                <div
                                    key={device.ip_address}
                                    className="flex shrink-0 items-center gap-2 rounded-md border border-border bg-card px-2.5 py-1.5"
                                >
                                    <div className="min-w-0">
                                        <p className="mono text-[12px]">{device.ip_address}</p>
                                        <p className="max-w-[160px] truncate text-[10px] text-muted-foreground">
                                            {[device.name, device.serial_number ? `S/N ${device.serial_number}` : null, device.firmware]
                                                .filter(Boolean)
                                                .join(' · ') || 'ZKTeco device'}
                                        </p>
                                    </div>
                                    {device.already_added ? (
                                        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-success">
                                            <Check className="size-3.5" /> Added
                                        </span>
                                    ) : (
                                        <Button size="sm" onClick={() => addDiscovered(device)}>
                                            Add
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </SubBar>
            )}

            <PageScroll>
                {devices.length === 0 ? (
                    <div className="flex h-full items-center justify-center p-10 text-center text-[13px] text-muted-foreground">
                        No devices yet. Add your first ZKTeco terminal, or scan the network.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Address</TableHead>
                                <TableHead>Serial</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Last checked</TableHead>
                                <TableHead className="text-end">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {devices.map((device) => (
                                <TableRow key={device.id} className={cn(testingId === device.id && 'bg-accent-brand/5')}>
                                    <TableCell className="font-medium">
                                        <Link href={`/devices/${device.id}/users`} className="hover:text-accent-brand hover:underline">
                                            {device.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="mono text-muted-foreground">
                                        {device.ip_address}:{device.port}
                                    </TableCell>
                                    <TableCell className="mono text-muted-foreground">{device.serial_number ?? '—'}</TableCell>
                                    <TableCell>
                                        {testingId === device.id ? (
                                            <StatusBadge tone="info">Connecting</StatusBadge>
                                        ) : device.last_connection_ok ? (
                                            <StatusBadge tone="success">Online</StatusBadge>
                                        ) : (
                                            <StatusBadge tone="idle">Unknown</StatusBadge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{formatDate(device.last_connected_at)}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/devices/${device.id}/users`}>
                                                    <Users className="size-4" /> Users
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={testingId === device.id}
                                                onClick={() => test(device)}
                                            >
                                                {testingId === device.id ? (
                                                    <span className="inline-flex items-center gap-1.5">
                                                        <ConnectingDots /> Testing
                                                    </span>
                                                ) : (
                                                    'Test'
                                                )}
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="opacity-0 group-hover:opacity-100"
                                                onClick={() => openEdit(device)}
                                                title="Edit"
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground opacity-0 hover:text-danger group-hover:opacity-100"
                                                onClick={() => remove(device)}
                                                title="Delete"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </PageScroll>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingId ? 'Edit device' : 'Add device'}</DialogTitle>
                    </DialogHeader>

                    <form className="space-y-3" onSubmit={submit}>
                        <div className="space-y-1.5">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={form.name}
                                placeholder="Front door terminal"
                                onChange={(event) => set('name', event.target.value)}
                            />
                            {errors.name && <p className="text-[11px] text-danger">{errors.name}</p>}
                        </div>

                        <div className="grid grid-cols-3 gap-3">
                            <div className="col-span-2 space-y-1.5">
                                <Label htmlFor="ip">IP address</Label>
                                <Input
                                    id="ip"
                                    value={form.ip_address}
                                    placeholder="192.168.1.201"
                                    className="mono"
                                    onChange={(event) => set('ip_address', event.target.value)}
                                />
                                {errors.ip_address && <p className="text-[11px] text-danger">{errors.ip_address}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="port">Port</Label>
                                <Input
                                    id="port"
                                    type="number"
                                    value={form.port}
                                    onChange={(event) => set('port', event.target.value)}
                                />
                                {errors.port && <p className="text-[11px] text-danger">{errors.port}</p>}
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="comm_key">
                                Communication key <span className="text-muted-foreground">(optional)</span>
                            </Label>
                            <Input
                                id="comm_key"
                                type="number"
                                value={form.comm_key}
                                placeholder="Leave blank if the device has none"
                                onChange={(event) => set('comm_key', event.target.value)}
                            />
                            {errors.comm_key && <p className="text-[11px] text-danger">{errors.comm_key}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="notes">
                                Notes <span className="text-muted-foreground">(optional)</span>
                            </Label>
                            <Textarea id="notes" rows={2} value={form.notes} onChange={(event) => set('notes', event.target.value)} />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving…' : 'Save device'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </Page>
    );
}
