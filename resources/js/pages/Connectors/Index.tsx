import { type FormEvent, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Check, DownloadCloud, Loader2, Monitor, Plug, RefreshCw, ShieldCheck, Unplug } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ConnectingDots } from '@/components/connecting-dots';
import { FullnessMark } from '@/components/fullness-mark';
import { StatusBadge } from '@/components/status-badge';
import { Page, PageScroll, Toolbar } from '@/components/shell/page';
import { usePageStatus } from '@/components/shell/shell-status';
import { cn } from '@/lib/utils';

interface Tenant {
    id: string;
    name: string;
    role?: string | null;
    role_id?: number | null;
    logo_url?: string | null;
}

interface FullnessDevice {
    id: number | string;
    name: string;
    serial_number?: string | null;
    connection_mode?: string | null;
    assigned_count?: number;
    online?: boolean;
}

interface Connection {
    base_url: string;
    owner_email: string | null;
    tenant_id: string | null;
    tenant_name: string | null;
    fullness_device_id: string | null;
    fullness_device_name: string | null;
    last_connected_at: string | null;
    last_synced_at: string | null;
}

interface Props {
    connection: Connection | null;
    tenants: Tenant[];
    devices: FullnessDevice[];
    defaultBaseUrl: string;
    deviceCount: number;
}

export default function ConnectorsIndex({ connection, tenants, devices, defaultBaseUrl, deviceCount }: Props) {
    const [open, setOpen] = useState(false);
    const [baseUrl, setBaseUrl] = useState(connection?.base_url ?? defaultBaseUrl);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [selectingId, setSelectingId] = useState<string | null>(null);
    const [selectingDeviceId, setSelectingDeviceId] = useState<string | null>(null);
    const [refreshingDevices, setRefreshingDevices] = useState(false);
    const [changingTenant, setChangingTenant] = useState(false);
    const [fetching, setFetching] = useState(false);

    const isConnected = Boolean(connection);
    const hasTenant = Boolean(connection?.tenant_id);
    const isReady = Boolean(connection?.fullness_device_id);

    usePageStatus(
        () => ({
            connection: (
                <span className="flex items-center gap-1.5">
                    <span className={cn('size-1.5 rounded-full', isConnected ? 'bg-success' : 'bg-idle')} />
                    {isConnected ? 'Fullness connected' : 'No connectors linked'}
                </span>
            ),
            counts: connection?.tenant_name ? <span className="truncate">{connection.tenant_name}</span> : undefined,
        }),
        [isConnected, connection?.tenant_name],
    );

    const connect = (event: FormEvent) => {
        event.preventDefault();
        router.post(
            '/connectors/connect',
            { base_url: baseUrl, email, password },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setPassword('');
                },
                onError: (formErrors) => setErrors(formErrors),
                onSuccess: () => setErrors({}),
            },
        );
    };

    const selectTenant = (tenant: Tenant) => {
        router.post(
            '/connectors/tenant',
            { tenant_id: tenant.id },
            {
                preserveScroll: true,
                onStart: () => setSelectingId(tenant.id),
                onFinish: () => setSelectingId(null),
                onSuccess: () => setChangingTenant(false),
            },
        );
    };

    const selectDevice = (device: FullnessDevice) => {
        router.post(
            '/connectors/device',
            { device_id: device.id },
            {
                preserveScroll: true,
                onStart: () => setSelectingDeviceId(String(device.id)),
                onFinish: () => setSelectingDeviceId(null),
            },
        );
    };

    // Re-pull the device list: a device added, renamed, or newly assigned users in
    // the CRM after this screen was opened. The current selection survives.
    const refreshDevices = () => {
        router.post(
            '/connectors/devices/refresh',
            {},
            {
                preserveScroll: true,
                // Without this Inertia remounts the page on POST, resetting local
                // state — which closes this dialog. Refreshing the list only to be
                // thrown out of the picker would make the button pointless.
                preserveState: true,
                onStart: () => setRefreshingDevices(true),
                onFinish: () => setRefreshingDevices(false),
            },
        );
    };

    const fetchUsers = () => {
        router.post('/connectors/fetch', {}, { onStart: () => setFetching(true), onFinish: () => setFetching(false) });
    };

    const disconnect = () => {
        if (!window.confirm('Disconnect from Fullness? The saved access token will be removed from this computer.')) {
            return;
        }
        router.delete('/connectors', { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <Page>
            <Head title="Connectors" />

            <Toolbar>
                <Plug className="size-4 text-accent-brand" />
                <span className="text-[13px] font-semibold">Connectors</span>
                <span className="rounded-sm bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">1</span>
            </Toolbar>

            <PageScroll>
                <div className="mx-auto w-full max-w-2xl space-y-4 p-4">
                    <div>
                        <h1 className="text-[15px] font-semibold">Available connectors</h1>
                        <p className="mt-1 text-[12px] text-muted-foreground">
                            Link an external application to pull users directly into this app — no spreadsheet needed.
                            Select a connector to set it up.
                        </p>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        {/* Fullness — the available connector */}
                        <button
                            type="button"
                            onClick={() => setOpen(true)}
                            className="group flex flex-col rounded-lg border border-border bg-card p-4 text-start transition-colors hover:border-accent-brand hover:bg-accent/30"
                        >
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-md border border-border bg-background">
                                    <FullnessMark className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[14px] font-semibold">Fullness</p>
                                    <p className="text-[11px] text-muted-foreground">Attendance CRM</p>
                                </div>
                                <StatusBadge tone={isConnected ? 'success' : 'info'}>
                                    {isConnected ? 'Connected' : 'Available'}
                                </StatusBadge>
                            </div>
                            <p className="mt-3 text-[12px] leading-relaxed text-muted-foreground">
                                Sign in with your Fullness business account and sync its assigned attendance users to a
                                ZKTeco device.
                            </p>
                            {isConnected && connection?.tenant_name && (
                                <p className="mt-2 text-[11px] text-foreground">
                                    Business: <span className="font-medium">{connection.tenant_name}</span>
                                </p>
                            )}
                            <span className="mt-3 inline-flex items-center gap-1 text-[12px] font-medium text-accent-brand">
                                {isConnected ? 'Manage' : 'Connect'} →
                            </span>
                        </button>

                        {/* Placeholder for future connectors */}
                        <div className="flex flex-col rounded-lg border border-dashed border-border p-4 opacity-70">
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                    <Plug className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[14px] font-semibold">More integrations</p>
                                    <p className="text-[11px] text-muted-foreground">Coming soon</p>
                                </div>
                            </div>
                            <p className="mt-3 text-[12px] leading-relaxed text-muted-foreground">
                                Additional connectors will appear here as they become available.
                            </p>
                        </div>
                    </div>
                </div>
            </PageScroll>

            {/* Connection popup for the selected connector (Fullness) */}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            <span className="flex items-center gap-2">
                                <FullnessMark className="size-4" /> Connect to Fullness
                            </span>
                        </DialogTitle>
                    </DialogHeader>

                    {/* Step 1 — sign in */}
                    {!isConnected && (
                        <form onSubmit={connect} className="space-y-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="base_url">Fullness address</Label>
                                <Input
                                    id="base_url"
                                    value={baseUrl}
                                    className="mono"
                                    placeholder="https://fullness.co.il"
                                    onChange={(event) => setBaseUrl(event.target.value)}
                                />
                                {errors.base_url && <p className="text-[11px] text-danger">{errors.base_url}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="email">Business owner email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="username"
                                    value={email}
                                    placeholder="owner@business.co.il"
                                    onChange={(event) => setEmail(event.target.value)}
                                />
                                {errors.email && <p className="text-[11px] text-danger">{errors.email}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={password}
                                    onChange={(event) => setPassword(event.target.value)}
                                />
                                {errors.password && <p className="text-[11px] text-danger">{errors.password}</p>}
                            </div>
                            <div className="flex items-start gap-2 rounded-md bg-muted px-3 py-2 text-[11px] text-muted-foreground">
                                <ShieldCheck className="mt-0.5 size-3.5 shrink-0 text-success" />
                                <span>
                                    Your password is sent securely to Fullness and never stored on this computer — only a
                                    revocable access token is kept.
                                </span>
                            </div>
                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing || !email || !password}>
                                    {processing ? (
                                        <span className="inline-flex items-center gap-1.5">
                                            <ConnectingDots /> Connecting
                                        </span>
                                    ) : (
                                        'Connect'
                                    )}
                                </Button>
                            </div>
                        </form>
                    )}

                    {/* Step 2 — choose the business */}
                    {isConnected && (!hasTenant || changingTenant) && (
                        <div className="space-y-3">
                            {connection?.owner_email && (
                                <p className="text-[11px] text-muted-foreground">
                                    Signed in as <span className="font-medium text-foreground">{connection.owner_email}</span>
                                </p>
                            )}
                            <p className="text-[12px] font-medium">Choose the business to sync</p>
                            <div className="space-y-1.5">
                                {tenants.map((tenant) => (
                                    <button
                                        key={tenant.id}
                                        type="button"
                                        onClick={() => selectTenant(tenant)}
                                        disabled={selectingId !== null}
                                        className="flex w-full items-center gap-3 rounded-md border border-border px-3 py-2 text-start transition-colors hover:border-accent-brand hover:bg-accent/40"
                                    >
                                        <Building2 className="size-4 shrink-0 text-muted-foreground" />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-[13px] font-medium">{tenant.name}</p>
                                            {tenant.role && (
                                                <p className="text-[11px] capitalize text-muted-foreground">{tenant.role}</p>
                                            )}
                                        </div>
                                        {selectingId === tenant.id && (
                                            <Loader2 className="size-4 animate-spin text-muted-foreground" />
                                        )}
                                    </button>
                                ))}
                                {tenants.length === 0 && (
                                    <p className="text-[12px] text-muted-foreground">No businesses are available for this account.</p>
                                )}
                            </div>
                            <div className="flex justify-between pt-1">
                                <Button variant="ghost" size="sm" onClick={disconnect}>
                                    <Unplug className="size-4" /> Disconnect
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Step 3 — choose the device, then fetch & sync */}
                    {hasTenant && !changingTenant && (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-[11px] text-muted-foreground">
                                    Business: <span className="font-medium text-foreground">{connection?.tenant_name}</span>
                                </p>
                                {tenants.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => setChangingTenant(true)}
                                        className="text-[11px] font-medium text-accent-brand hover:underline"
                                    >
                                        Change
                                    </button>
                                )}
                            </div>

                            <div className="flex items-center justify-between gap-2">
                                <p className="text-[12px] font-medium">Choose the device to sync</p>
                                <button
                                    type="button"
                                    onClick={refreshDevices}
                                    disabled={refreshingDevices || selectingDeviceId !== null}
                                    className="inline-flex items-center gap-1 text-[11px] font-medium text-accent-brand hover:underline disabled:opacity-60"
                                    title="Re-pull the device list from Fullness"
                                >
                                    <RefreshCw className={cn('size-3', refreshingDevices && 'animate-spin')} />
                                    {refreshingDevices ? 'Refreshing' : 'Refresh'}
                                </button>
                            </div>
                            <div className="space-y-1.5">
                                {devices.map((device) => {
                                    const active = String(device.id) === connection?.fullness_device_id;
                                    return (
                                        <button
                                            key={device.id}
                                            type="button"
                                            onClick={() => selectDevice(device)}
                                            disabled={selectingDeviceId !== null}
                                            className={cn(
                                                'flex w-full items-center gap-3 rounded-md border px-3 py-2 text-start transition-colors',
                                                active
                                                    ? 'border-accent-brand bg-accent-brand/5'
                                                    : 'border-border hover:border-accent-brand hover:bg-accent/40',
                                            )}
                                        >
                                            <Monitor
                                                className={cn('size-4 shrink-0', active ? 'text-accent-brand' : 'text-muted-foreground')}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-[13px] font-medium">{device.name}</p>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {device.assigned_count ?? 0} user{device.assigned_count === 1 ? '' : 's'}
                                                    {device.serial_number ? ` · ${device.serial_number}` : ''}
                                                </p>
                                            </div>
                                            {selectingDeviceId === String(device.id) ? (
                                                <Loader2 className="size-4 animate-spin text-muted-foreground" />
                                            ) : active ? (
                                                <Check className="size-4 text-accent-brand" />
                                            ) : null}
                                        </button>
                                    );
                                })}
                                {devices.length === 0 && (
                                    <p className="text-[12px] text-muted-foreground">
                                        This business has no attendance devices in Fullness.
                                    </p>
                                )}
                            </div>

                            {deviceCount === 0 && (
                                <p className="rounded-md bg-muted px-3 py-2 text-[12px] text-warning">
                                    No local devices yet.{' '}
                                    <Link href="/devices" className="font-medium underline">
                                        Add a device
                                    </Link>{' '}
                                    to push the fetched users.
                                </p>
                            )}

                            <div className="flex items-center justify-between pt-1">
                                <Button variant="ghost" size="sm" onClick={disconnect}>
                                    <Unplug className="size-4" /> Disconnect
                                </Button>
                                <Button onClick={fetchUsers} disabled={!isReady || fetching}>
                                    {fetching ? (
                                        <span className="inline-flex items-center gap-1.5">
                                            <ConnectingDots /> Fetching
                                        </span>
                                    ) : (
                                        <>
                                            <DownloadCloud className="size-4" /> Fetch &amp; sync
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </Page>
    );
}
