import { type FormEvent, type ReactNode, useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Plus, RefreshCw, Send, Trash2 } from 'lucide-react';

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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { AnimatedNumber } from '@/components/animated-number';
import { StatusBadge } from '@/components/status-badge';
import { SyncFlow } from '@/components/sync-flow';
import { Page, PageScroll, SubBar, Toolbar } from '@/components/shell/page';
import { usePageStatus } from '@/components/shell/shell-status';
import { cn } from '@/lib/utils';
import type { BatchSummary, DeviceLite, ImportedUserRow, Paginated } from '@/types';

interface Props {
    batch: BatchSummary;
    users: Paginated<ImportedUserRow>;
    devices: DeviceLite[];
}

interface UserForm {
    user_id: string;
    name: string;
    password: string;
    card_number: string;
    privilege: string;
}

const EMPTY_USER: UserForm = { user_id: '', name: '', password: '', card_number: '', privilege: 'user' };

function Metric({ label, value, className }: { label: string; value: number; className?: string }) {
    return (
        <div className="flex flex-col leading-tight">
            <span className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</span>
            <span className={cn('text-[15px] font-semibold tabular-nums', className)}>
                <AnimatedNumber value={value} />
            </span>
        </div>
    );
}

export default function ImportShow({ batch, users, devices }: Props) {
    const [deviceId, setDeviceId] = useState<string>(String(batch.device?.id ?? devices[0]?.id ?? ''));
    const timer = useRef<number | null>(null);

    const [userDialogOpen, setUserDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<ImportedUserRow | null>(null);
    const [form, setForm] = useState<UserForm>(EMPTY_USER);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const isSyncing = batch.status === 'syncing';
    const canSync = devices.length > 0 && batch.valid_rows > 0 && !isSyncing;
    const showFlow = isSyncing || batch.status === 'completed' || batch.status === 'failed';
    const deviceName = batch.device?.name ?? devices.find((device) => String(device.id) === deviceId)?.name;

    const prevLink = users.links[0];
    const nextLink = users.links[users.links.length - 1];

    usePageStatus(
        () => {
            const counts: ReactNode =
                users.last_page > 1 ? (
                    <span className="flex items-center gap-1">
                        <button
                            type="button"
                            disabled={!prevLink?.url}
                            onClick={() => prevLink?.url && router.visit(prevLink.url, { preserveScroll: true, preserveState: true })}
                            className="flex size-4 items-center justify-center rounded-sm hover:bg-accent disabled:opacity-30"
                        >
                            <ChevronLeft className="size-3.5" />
                        </button>
                        <span className="tabular-nums">
                            Page {users.current_page} of {users.last_page}
                        </span>
                        <button
                            type="button"
                            disabled={!nextLink?.url}
                            onClick={() => nextLink?.url && router.visit(nextLink.url, { preserveScroll: true, preserveState: true })}
                            className="flex size-4 items-center justify-center rounded-sm hover:bg-accent disabled:opacity-30"
                        >
                            <ChevronRight className="size-3.5" />
                        </button>
                    </span>
                ) : (
                    <span className="tabular-nums">{users.total} rows</span>
                );

            return {
                sync: isSyncing ? (
                    <span className="tabular-nums">
                        Syncing {batch.synced_count + batch.failed_count} / {batch.valid_rows}
                    </span>
                ) : undefined,
                counts,
            };
        },
        [isSyncing, batch.synced_count, batch.failed_count, batch.valid_rows, users.current_page, users.last_page, users.total, prevLink?.url, nextLink?.url],
    );

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

        router.post(`/import/${batch.id}/sync`, { device_id: Number(deviceId) }, { preserveScroll: true, preserveState: true });
    };

    const set = <K extends keyof UserForm>(key: K, value: UserForm[K]) =>
        setForm((current) => ({ ...current, [key]: value }));

    const openAddUser = () => {
        setEditingUser(null);
        setForm(EMPTY_USER);
        setFormErrors({});
        setUserDialogOpen(true);
    };

    const openEditUser = (user: ImportedUserRow) => {
        setEditingUser(user);
        setForm({
            user_id: user.user_id,
            name: user.name,
            password: user.password ?? '',
            card_number: user.card_number ?? '',
            privilege: user.privilege || 'user',
        });
        setFormErrors({});
        setUserDialogOpen(true);
    };

    const submitUser = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onError: (errors: Record<string, string>) => setFormErrors(errors),
            onSuccess: () => setUserDialogOpen(false),
        };

        if (editingUser) {
            router.put(`/import/${batch.id}/users/${editingUser.id}`, form, options);
        } else {
            router.post(`/import/${batch.id}/users`, form, options);
        }
    };

    const deleteUser = (user: ImportedUserRow) => {
        if (!window.confirm(`Remove ${user.name || 'this row'} from the import?`)) {
            return;
        }

        router.delete(`/import/${batch.id}/users/${user.id}`, { preserveScroll: true });
    };

    const rowStatus = (user: ImportedUserRow) => (!user.is_valid ? 'skipped' : user.sync_status);

    return (
        <Page>
            <Head title={batch.original_filename} />

            <Toolbar>
                <div className="flex min-w-0 items-center gap-1.5 text-[13px]">
                    <Link href="/import" className="text-muted-foreground hover:text-foreground">
                        Imports
                    </Link>
                    <ChevronRight className="size-3.5 shrink-0 text-muted-foreground" />
                    <span className="truncate font-semibold">{batch.original_filename}</span>
                    <StatusBadge status={batch.status} className="ms-1" />
                </div>

                <div className="ms-auto flex items-center gap-2">
                    <Select value={deviceId} onValueChange={setDeviceId} disabled={isSyncing || devices.length === 0}>
                        <SelectTrigger className="w-52">
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
                    <Button onClick={sync} disabled={!canSync || !deviceId}>
                        <RefreshCw className={cn('size-4', isSyncing && 'animate-spin')} />
                        {isSyncing ? 'Syncing…' : `Sync ${batch.valid_rows}`}
                    </Button>
                    {batch.valid_rows > 0 && (
                        <Button variant="outline" size="icon" asChild title="Transfer view">
                            <Link href={`/import/${batch.id}/transfer`}>
                                <Send className="size-4" />
                            </Link>
                        </Button>
                    )}
                </div>
            </Toolbar>

            <SubBar className="gap-6">
                <Metric label="Total" value={batch.total_rows} />
                <Metric label="Valid" value={batch.valid_rows} className="text-success" />
                <Metric label="Invalid" value={batch.invalid_rows} className="text-danger" />
                <Metric label="Synced" value={batch.synced_count} className="text-accent-brand" />
                {batch.failed_count > 0 && <Metric label="Failed" value={batch.failed_count} className="text-danger" />}

                {devices.length === 0 && (
                    <span className="text-[12px] text-warning">
                        <Link href="/devices" className="underline">
                            Add a device
                        </Link>{' '}
                        to sync.
                    </span>
                )}

                <Button variant="outline" size="sm" className="ms-auto" onClick={openAddUser} disabled={isSyncing}>
                    <Plus className="size-4" /> Add user
                </Button>
            </SubBar>

            {showFlow && (
                <SubBar>
                    <SyncFlow
                        status={batch.status}
                        total={batch.valid_rows}
                        synced={batch.synced_count}
                        failed={batch.failed_count}
                        deviceName={deviceName}
                    />
                </SubBar>
            )}

            <PageScroll>
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
                            <TableHead className="text-end">Edit</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.data.map((user) => (
                            <TableRow key={user.id} className={cn(!user.is_valid && 'bg-danger-soft/40')}>
                                <TableCell className="mono text-muted-foreground">{user.row_number}</TableCell>
                                <TableCell className="mono">{user.user_id || '—'}</TableCell>
                                <TableCell>{user.name}</TableCell>
                                <TableCell className="mono text-muted-foreground">{user.name_ascii || '—'}</TableCell>
                                <TableCell className="mono text-muted-foreground">{user.password || '—'}</TableCell>
                                <TableCell className="mono text-muted-foreground">{user.card_number || '—'}</TableCell>
                                <TableCell>
                                    {user.privilege === 'admin' ? (
                                        <StatusBadge tone="info">Admin</StatusBadge>
                                    ) : (
                                        <span className="text-muted-foreground">User</span>
                                    )}
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-col gap-0.5">
                                        <StatusBadge status={rowStatus(user)} />
                                        {!user.is_valid && user.validation_errors.length > 0 ? (
                                            <span className="text-[11px] text-danger">{user.validation_errors.join(', ')}</span>
                                        ) : (
                                            user.sync_error && <span className="text-[11px] text-danger">{user.sync_error}</span>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="opacity-0 group-hover:opacity-100"
                                            onClick={() => openEditUser(user)}
                                            disabled={isSyncing}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-muted-foreground opacity-0 hover:text-danger group-hover:opacity-100"
                                            onClick={() => deleteUser(user)}
                                            disabled={isSyncing}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </PageScroll>

            <Dialog open={userDialogOpen} onOpenChange={setUserDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingUser ? 'Edit user' : 'Add user'}</DialogTitle>
                    </DialogHeader>

                    <form className="space-y-3" onSubmit={submitUser}>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="user_id">User ID</Label>
                                <Input
                                    id="user_id"
                                    value={form.user_id}
                                    placeholder="e.g. 1001"
                                    className="mono"
                                    onChange={(event) => set('user_id', event.target.value)}
                                />
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
                            <Input
                                id="name"
                                value={form.name}
                                placeholder="Dana Cohen"
                                onChange={(event) => set('name', event.target.value)}
                            />
                            {formErrors.name && <p className="text-[11px] text-danger">{formErrors.name}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="password">PIN <span className="text-muted-foreground">(optional)</span></Label>
                                <Input
                                    id="password"
                                    value={form.password}
                                    placeholder="≤ 8 digits"
                                    className="mono"
                                    onChange={(event) => set('password', event.target.value)}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="card_number">Card <span className="text-muted-foreground">(optional)</span></Label>
                                <Input
                                    id="card_number"
                                    value={form.card_number}
                                    placeholder="≤ 10 digits"
                                    className="mono"
                                    onChange={(event) => set('card_number', event.target.value)}
                                />
                            </div>
                        </div>

                        <p className="text-[11px] text-muted-foreground">
                            Device limits: user id ≤ 9 digits, name ≤ 24 chars (auto-converted), PIN ≤ 8 digits. Rows that
                            break a rule are saved but flagged invalid until fixed.
                        </p>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setUserDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saving}>
                                {saving ? 'Saving…' : 'Save user'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </Page>
    );
}
