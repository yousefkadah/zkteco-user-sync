import { type FormEvent, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StatusBadge } from '@/components/status-badge';
import { ConnectingDots } from '@/components/connecting-dots';
import { DataStream } from '@/components/data-stream';
import { Page, PageScroll, Toolbar } from '@/components/shell/page';
import { usePageStatus } from '@/components/shell/shell-status';
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

interface UserForm {
    user_id: string;
    name: string;
    password: string;
    card_number: string;
    privilege: string;
}

export default function DevicesUsers({ device, result }: Props) {
    const [refreshing, setRefreshing] = useState(false);
    const [busy, setBusy] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [editingUid, setEditingUid] = useState<number | null>(null);
    const [form, setForm] = useState<UserForm>({ user_id: '', name: '', password: '', card_number: '', privilege: 'user' });
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [deletingUid, setDeletingUid] = useState<number | null>(null);

    const adminCount = result.users.filter((user) => user.role === 14).length;

    usePageStatus(
        () => ({
            connection: refreshing ? (
                <span className="flex items-center gap-2">
                    <DataStream direction="to-app" active variant="inline" className="w-16" />
                    Reading device…
                </span>
            ) : busy ? (
                <span className="flex items-center gap-2">
                    <DataStream direction="to-device" active variant="inline" className="w-16" />
                    Writing to device…
                </span>
            ) : result.ok ? (
                <span className="flex items-center gap-1.5">
                    <span className="size-1.5 rounded-full bg-success" /> Connected
                </span>
            ) : (
                <span className="flex items-center gap-1.5">
                    <span className="size-1.5 rounded-full bg-danger" /> Unreachable
                </span>
            ),
            counts: result.ok ? (
                <span className="tabular-nums">
                    {result.count} user{result.count === 1 ? '' : 's'} · {adminCount} admin
                </span>
            ) : undefined,
        }),
        [refreshing, busy, result.ok, result.count, adminCount],
    );

    const set = <K extends keyof UserForm>(key: K, value: UserForm[K]) =>
        setForm((current) => ({ ...current, [key]: value }));

    const refresh = () => {
        router.reload({ onStart: () => setRefreshing(true), onFinish: () => setRefreshing(false) });
    };

    const openAdd = () => {
        setEditingUid(null);
        setForm({ user_id: '', name: '', password: '', card_number: '', privilege: 'user' });
        setFormErrors({});
        setEditOpen(true);
    };

    const openEdit = (user: DeviceUser) => {
        setEditingUid(user.uid);
        setForm({
            user_id: user.user_id,
            name: user.name,
            password: user.password ?? '',
            card_number: user.card_no ?? '',
            privilege: user.role === 14 ? 'admin' : 'user',
        });
        setFormErrors({});
        setEditOpen(true);
    };

    const submitEdit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onStart: () => {
                setSaving(true);
                setBusy(true);
            },
            onFinish: () => {
                setSaving(false);
                setBusy(false);
            },
            onError: (errors: Record<string, string>) => setFormErrors(errors),
            onSuccess: () => setEditOpen(false),
        };

        // editingUid === null → add a new user to the next free slot; otherwise overwrite the slot.
        if (editingUid === null) {
            router.post(`/devices/${device.id}/users`, form, options);
        } else {
            router.put(`/devices/${device.id}/users/${editingUid}`, form, options);
        }
    };

    const remove = (user: DeviceUser) => {
        if (!window.confirm(`Remove "${user.name || user.user_id}" from ${device.name}?`)) {
            return;
        }

        router.delete(`/devices/${device.id}/users/${user.uid}`, {
            preserveScroll: true,
            onStart: () => {
                setDeletingUid(user.uid);
                setBusy(true);
            },
            onFinish: () => {
                setDeletingUid(null);
                setBusy(false);
            },
        });
    };

    const clearAll = () => {
        if (!window.confirm(`Remove ALL ${result.count} users from ${device.name}? This cannot be undone.`)) {
            return;
        }

        router.delete(`/devices/${device.id}/users`, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <Page>
            <Head title={`Users on ${device.name}`} />

            <Toolbar>
                <div className="flex min-w-0 items-center gap-1.5 text-[13px]">
                    <Link href="/devices" className="text-muted-foreground hover:text-foreground">
                        Devices
                    </Link>
                    <ChevronRight className="size-3.5 shrink-0 text-muted-foreground" />
                    <span className="truncate font-semibold">{device.name}</span>
                    <span className="mono ms-1 rounded-sm bg-muted px-1.5 text-[11px] text-muted-foreground">
                        {device.ip_address}:{device.port}
                    </span>
                </div>

                <div className="ms-auto flex items-center gap-2">
                    {result.ok && result.users.length > 0 && (
                        <Button variant="outline" size="sm" className="text-danger hover:text-danger" disabled={busy} onClick={clearAll}>
                            <Trash2 className="size-4" /> Remove all
                        </Button>
                    )}
                    <Button variant="outline" size="sm" onClick={refresh} disabled={refreshing || busy}>
                        <RefreshCw className={cn('size-4', refreshing && 'animate-spin')} />
                        {refreshing ? 'Reading…' : 'Refresh'}
                    </Button>
                    {result.ok && (
                        <Button size="sm" onClick={openAdd} disabled={busy}>
                            <Plus className="size-4" /> Add user
                        </Button>
                    )}
                </div>
            </Toolbar>

            <PageScroll>
                {!result.ok ? (
                    <div className="flex h-full flex-col items-center justify-center gap-1 p-10 text-center">
                        <p className="text-[13px] font-medium text-danger">{result.error ?? 'Could not read the device.'}</p>
                        <p className="text-[12px] text-muted-foreground">
                            Make sure the terminal is powered on and on this network, then Refresh.
                        </p>
                    </div>
                ) : result.users.length === 0 ? (
                    <div className="flex h-full items-center justify-center p-10 text-center text-[13px] text-muted-foreground">
                        No users are stored on this device yet.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Slot</TableHead>
                                <TableHead>User ID</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>PIN</TableHead>
                                <TableHead>Card</TableHead>
                                <TableHead className="text-end">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {result.users.map((user) => (
                                <TableRow key={user.uid}>
                                    <TableCell className="mono text-muted-foreground">{user.uid}</TableCell>
                                    <TableCell className="mono">{user.user_id || '—'}</TableCell>
                                    <TableCell>{user.name || '—'}</TableCell>
                                    <TableCell>
                                        {user.role === 14 ? (
                                            <StatusBadge tone="info">Admin</StatusBadge>
                                        ) : (
                                            <span className="text-muted-foreground">{user.role_label}</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="mono text-muted-foreground">{user.password || '—'}</TableCell>
                                    <TableCell className="mono text-muted-foreground">{user.card_no ?? '—'}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="opacity-0 group-hover:opacity-100"
                                                disabled={busy}
                                                onClick={() => openEdit(user)}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground opacity-0 hover:text-danger group-hover:opacity-100"
                                                disabled={busy}
                                                onClick={() => remove(user)}
                                            >
                                                {deletingUid === user.uid ? <ConnectingDots /> : <Trash2 className="size-4" />}
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </PageScroll>

            <Dialog open={editOpen} onOpenChange={setEditOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingUid === null ? 'Add user to device' : 'Edit user on device'}</DialogTitle>
                    </DialogHeader>

                    <form className="space-y-3" onSubmit={submitEdit}>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="user_id">User ID</Label>
                                <Input id="user_id" value={form.user_id} className="mono" onChange={(event) => set('user_id', event.target.value)} />
                                {formErrors.user_id && <p className="text-[11px] text-danger">{formErrors.user_id}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="privilege">Role</Label>
                                <Select value={form.privilege} onValueChange={(value) => set('privilege', value)}>
                                    <SelectTrigger id="privilege">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="user">User</SelectItem>
                                        <SelectItem value="admin">Admin</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" value={form.name} onChange={(event) => set('name', event.target.value)} />
                            {formErrors.name && <p className="text-[11px] text-danger">{formErrors.name}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="password">PIN <span className="text-muted-foreground">(optional)</span></Label>
                                <Input id="password" value={form.password} className="mono" onChange={(event) => set('password', event.target.value)} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="card_number">Card <span className="text-muted-foreground">(optional)</span></Label>
                                <Input id="card_number" value={form.card_number} className="mono" onChange={(event) => set('card_number', event.target.value)} />
                            </div>
                        </div>

                        <p className="text-[11px] text-muted-foreground">
                            {editingUid === null
                                ? 'The user is written to the next free slot on the device. '
                                : `Saving overwrites slot ${editingUid} on the device. `}
                            Non-ASCII names are auto-converted; user id ≤ 9 digits, PIN ≤ 8 digits.
                        </p>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setEditOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saving}>
                                {saving ? (
                                    <span className="inline-flex items-center gap-1.5">
                                        <ConnectingDots /> {editingUid === null ? 'Adding' : 'Saving'}
                                    </span>
                                ) : editingUid === null ? (
                                    'Add to device'
                                ) : (
                                    'Save to device'
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </Page>
    );
}
