import { type DragEvent, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Trash2, UploadCloud } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConnectingDots } from '@/components/connecting-dots';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StatusBadge } from '@/components/status-badge';
import type { BatchSummary, DeviceLite } from '@/types';

interface Props {
    batches: BatchSummary[];
    devices: DeviceLite[];
}

export default function ImportIndex({ batches, devices }: Props) {
    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

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
        <>
            <Head title="Imports" />

            <header className="mb-6">
                <h1 className="text-2xl font-semibold">Imports</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Upload an Excel file of users, review it, then push it to a ZKTeco device.
                </p>
            </header>

            <Card>
                <CardHeader className="flex-row items-center justify-between space-y-0">
                    <CardTitle className="text-sm">Upload a user file</CardTitle>
                    <a href="/template" className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                        <Download className="size-4" />
                        Download template
                    </a>
                </CardHeader>
                <CardContent>
                    <label
                        className={`flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors ${
                            dragOver ? 'border-primary bg-secondary' : 'border-border hover:border-muted-foreground/40'
                        }`}
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragOver(true);
                        }}
                        onDragLeave={() => setDragOver(false)}
                        onDrop={onDrop}
                    >
                        <UploadCloud className="size-8 text-muted-foreground" />
                        <p className="mt-3 text-sm">
                            <span className="font-medium text-primary">Choose a file</span> or drag it here
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            .xlsx, .xls or .csv — columns: user_id, name, password, card_number, privilege
                        </p>
                        <input
                            ref={inputRef}
                            type="file"
                            className="hidden"
                            accept=".xlsx,.xls,.csv"
                            onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                        />
                    </label>

                    {file && (
                        <div className="mt-4 flex items-center justify-between rounded-lg bg-secondary px-4 py-3">
                            <span className="truncate text-sm">{file.name}</span>
                            <Button onClick={submit} disabled={processing}>
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
                        <p className="mt-4 text-sm text-amber-600">
                            No devices yet.{' '}
                            <Link href="/devices" className="font-medium underline">
                                Add a device
                            </Link>{' '}
                            before you can sync.
                        </p>
                    )}
                </CardContent>
            </Card>

            <section className="mt-8">
                <h2 className="mb-3 text-sm font-semibold">Recent imports</h2>

                {batches.length === 0 ? (
                    <div className="rounded-xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
                        No imports yet. Upload a file to get started.
                    </div>
                ) : (
                    <Card className="overflow-hidden">
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
                                {batches.map((batch) => (
                                    <TableRow key={batch.id}>
                                        <TableCell>
                                            <Link
                                                href={`/import/${batch.id}`}
                                                className="font-medium hover:text-primary hover:underline"
                                            >
                                                {batch.original_filename}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            <span className="text-emerald-600">{batch.valid_rows} valid</span>
                                            {batch.invalid_rows > 0 && (
                                                <span className="text-rose-500"> · {batch.invalid_rows} invalid</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={batch.status} />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{batch.device?.name ?? '—'}</TableCell>
                                        <TableCell className="text-muted-foreground">{formatDate(batch.created_at)}</TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-destructive"
                                                onClick={() => remove(batch)}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                )}
            </section>
        </>
    );
}
