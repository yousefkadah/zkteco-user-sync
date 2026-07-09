import { type DragEvent, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Trash2, UploadCloud } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Segmented } from '@/components/ui/segmented';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StatusBadge } from '@/components/status-badge';
import { ConnectingDots } from '@/components/connecting-dots';
import { Page, PageScroll, Toolbar } from '@/components/shell/page';
import { usePageStatus } from '@/components/shell/shell-status';
import { cn } from '@/lib/utils';
import type { BatchSummary, DeviceLite } from '@/types';

interface Props {
    batches: BatchSummary[];
    devices: DeviceLite[];
}

type Filter = 'all' | 'pending' | 'done' | 'failed';

const bucket = (status: string): Exclude<Filter, 'all'> =>
    status === 'failed' ? 'failed' : status === 'completed' || status === 'synced' ? 'done' : 'pending';

export default function ImportIndex({ batches, devices }: Props) {
    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [filter, setFilter] = useState<Filter>('all');
    const inputRef = useRef<HTMLInputElement>(null);

    const filtered = useMemo(
        () => (filter === 'all' ? batches : batches.filter((batch) => bucket(batch.status) === filter)),
        [batches, filter],
    );

    usePageStatus(
        () => ({
            counts: (
                <span className="tabular-nums">
                    {batches.length} import{batches.length === 1 ? '' : 's'} · {devices.length} device
                    {devices.length === 1 ? '' : 's'}
                </span>
            ),
        }),
        [batches.length, devices.length],
    );

    const submit = () => {
        if (!file || processing) {
            return;
        }

        router.post(
            '/import',
            { file },
            {
                forceFormData: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setFile(null);
                    if (inputRef.current) {
                        inputRef.current.value = '';
                    }
                },
            },
        );
    };

    const onDrop = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setDragOver(false);
        setFile(event.dataTransfer.files[0] ?? null);
    };

    const remove = (batch: BatchSummary) => {
        if (!window.confirm(`Delete import "${batch.original_filename}"?`)) {
            return;
        }

        router.delete(`/import/${batch.id}`, { preserveScroll: true });
    };

    const formatDate = (iso: string | null) => (iso ? new Date(iso).toLocaleString() : '—');

    return (
        <Page>
            <Head title="Imports" />

            <Toolbar>
                <span className="text-[13px] font-semibold">Imports</span>
                <span className="rounded-sm bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">
                    {batches.length}
                </span>

                <div className="ms-auto flex items-center gap-2">
                    <Segmented
                        value={filter}
                        onChange={setFilter}
                        options={[
                            { label: 'All', value: 'all' },
                            { label: 'Pending', value: 'pending' },
                            { label: 'Done', value: 'done' },
                            { label: 'Failed', value: 'failed' },
                        ]}
                    />
                    <Button variant="outline" size="sm" asChild>
                        <a href="/template">
                            <Download className="size-4" /> Template
                        </a>
                    </Button>
                </div>
            </Toolbar>

            <PageScroll className="flex flex-col">
                {/* Upload band */}
                <div className="border-b border-border p-3">
                    <label
                        className={cn(
                            'flex cursor-pointer items-center justify-center gap-3 rounded-md border border-dashed px-4 py-4 text-center transition-colors',
                            dragOver
                                ? 'border-accent-brand bg-accent-brand/5'
                                : 'border-border hover:border-border-strong hover:bg-accent/40',
                        )}
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragOver(true);
                        }}
                        onDragLeave={() => setDragOver(false)}
                        onDrop={onDrop}
                    >
                        <UploadCloud className={cn('size-5 shrink-0', dragOver ? 'text-accent-brand' : 'text-muted-foreground')} />
                        <div className="text-start">
                            <p className="text-[13px]">
                                <span className="font-medium text-accent-brand">Choose a file</span> or drag it here
                            </p>
                            <p className="text-[11px] text-muted-foreground">
                                .xlsx, .xls or .csv — user_id, name, password, card_number, privilege
                            </p>
                        </div>
                        <input
                            ref={inputRef}
                            type="file"
                            className="hidden"
                            accept=".xlsx,.xls,.csv"
                            onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                        />
                    </label>

                    {file && (
                        <div className="mt-2 flex items-center justify-between gap-3 rounded-md bg-muted px-3 py-1.5">
                            <span className="selectable truncate text-[13px]">{file.name}</span>
                            <Button size="sm" onClick={submit} disabled={processing}>
                                {processing ? (
                                    <span className="inline-flex items-center gap-1.5">
                                        <ConnectingDots /> Importing
                                    </span>
                                ) : (
                                    'Import file'
                                )}
                            </Button>
                        </div>
                    )}

                    {devices.length === 0 && (
                        <p className="mt-2 text-[12px] text-warning">
                            No devices yet.{' '}
                            <Link href="/devices" className="font-medium underline">
                                Add a device
                            </Link>{' '}
                            before you can sync.
                        </p>
                    )}
                </div>

                {/* Recent imports */}
                {filtered.length === 0 ? (
                    <div className="flex flex-1 items-center justify-center p-10 text-center text-[13px] text-muted-foreground">
                        {batches.length === 0 ? 'No imports yet. Upload a file to get started.' : 'No imports match this filter.'}
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>File</TableHead>
                                <TableHead>Rows</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Device</TableHead>
                                <TableHead>Imported</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.map((batch) => (
                                <TableRow key={batch.id}>
                                    <TableCell>
                                        <Link
                                            href={`/import/${batch.id}`}
                                            className="font-medium hover:text-accent-brand hover:underline"
                                        >
                                            {batch.original_filename}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        <span className="text-success">{batch.valid_rows} valid</span>
                                        {batch.invalid_rows > 0 && (
                                            <span className="text-danger"> · {batch.invalid_rows} invalid</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={batch.status} />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{batch.device?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">{formatDate(batch.created_at)}</TableCell>
                                    <TableCell className="text-end">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-muted-foreground opacity-0 hover:text-danger group-hover:opacity-100"
                                            onClick={() => remove(batch)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </PageScroll>
        </Page>
    );
}
