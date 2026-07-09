<script>
import { Link, router } from '@inertiajs/vue3';
import StatusPill from '../../Components/StatusPill.vue';

export default {
    name: 'ImportIndex',
    components: { Link, StatusPill },
    props: {
        batches: { type: Array, default: () => [] },
        devices: { type: Array, default: () => [] },
    },
    data() {
        return {
            file: null,
            processing: false,
            dragOver: false,
        };
    },
    methods: {
        onFileChange(event) {
            this.file = event.target.files[0] ?? null;
        },
        onDrop(event) {
            this.dragOver = false;
            this.file = event.dataTransfer.files[0] ?? null;
        },
        submit() {
            if (!this.file || this.processing) {
                return;
            }

            router.post('/import', { file: this.file }, {
                forceFormData: true,
                onStart: () => {
                    this.processing = true;
                },
                onFinish: () => {
                    this.processing = false;
                    this.file = null;
                    if (this.$refs.fileInput) {
                        this.$refs.fileInput.value = '';
                    }
                },
            });
        },
        remove(batch) {
            if (!window.confirm(`Delete import "${batch.original_filename}"?`)) {
                return;
            }

            router.delete(`/import/${batch.id}`, { preserveScroll: true });
        },
        formatDate(iso) {
            return iso ? new Date(iso).toLocaleString() : '—';
        },
    },
};
</script>

<template>
    <div>
        <header class="mb-6">
            <h1 class="text-2xl font-semibold text-slate-900">Imports</h1>
            <p class="mt-1 text-sm text-slate-500">
                Upload an Excel file of users, review it, then push it to a ZKTeco device.
            </p>
        </header>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Upload a user file</h2>
                <a href="/template" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                    Download template
                </a>
            </div>

            <label
                class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 text-center transition"
                :class="dragOver ? 'border-blue-400 bg-blue-50' : 'border-slate-300 hover:border-slate-400'"
                @dragover.prevent="dragOver = true"
                @dragleave.prevent="dragOver = false"
                @drop.prevent="onDrop"
            >
                <svg class="size-8 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 16V4m0 0 4 4m-4-4-4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                <p class="mt-3 text-sm text-slate-600">
                    <span class="font-medium text-blue-600">Choose a file</span> or drag it here
                </p>
                <p class="mt-1 text-xs text-slate-400">.xlsx, .xls or .csv — columns: user_id, name, password, card_number, privilege</p>
                <input
                    ref="fileInput"
                    type="file"
                    class="hidden"
                    accept=".xlsx,.xls,.csv"
                    @change="onFileChange"
                >
            </label>

            <div v-if="file" class="mt-4 flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                <span class="truncate text-sm text-slate-700">{{ file.name }}</span>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    :disabled="processing"
                    @click="submit"
                >
                    {{ processing ? 'Importing…' : 'Import file' }}
                </button>
            </div>

            <p v-if="devices.length === 0" class="mt-4 text-sm text-amber-600">
                No devices yet.
                <Link href="/devices" class="font-medium underline">Add a device</Link>
                before you can sync.
            </p>
        </div>

        <section class="mt-8">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Recent imports</h2>

            <div v-if="batches.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-sm text-slate-500">
                No imports yet. Upload a file to get started.
            </div>

            <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Rows</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Device</th>
                            <th class="px-4 py-3">Imported</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="batch in batches" :key="batch.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <Link :href="`/import/${batch.id}`" class="font-medium text-slate-900 hover:text-blue-600">
                                    {{ batch.original_filename }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <span class="text-emerald-600">{{ batch.valid_rows }} valid</span>
                                <span v-if="batch.invalid_rows" class="text-rose-500"> · {{ batch.invalid_rows }} invalid</span>
                            </td>
                            <td class="px-4 py-3"><StatusPill :status="batch.status" /></td>
                            <td class="px-4 py-3 text-slate-600">{{ batch.device?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ formatDate(batch.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="text-slate-400 hover:text-rose-600" @click="remove(batch)">
                                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M8.75 1a1 1 0 0 0-.95.68L7.4 3H4a1 1 0 0 0 0 2h12a1 1 0 1 0 0-2h-3.4l-.4-1.32A1 1 0 0 0 11.25 1h-2.5ZM5.5 7a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-1.5 0v-7.5A.75.75 0 0 1 5.5 7Zm4.5 0a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-1.5 0v-7.5A.75.75 0 0 1 10 7Zm4.5.75a.75.75 0 0 0-1.5 0v7.5a.75.75 0 0 0 1.5 0v-7.5Z" /></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
