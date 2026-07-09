<script>
import { Link, router } from '@inertiajs/vue3';
import StatusPill from '../../Components/StatusPill.vue';

export default {
    name: 'ImportShow',
    components: { Link, StatusPill },
    props: {
        batch: { type: Object, required: true },
        users: { type: Object, required: true },
        devices: { type: Array, default: () => [] },
    },
    data() {
        return {
            selectedDeviceId: this.batch.device?.id ?? this.devices[0]?.id ?? null,
            pollTimer: null,
        };
    },
    computed: {
        isSyncing() {
            return this.batch.status === 'syncing';
        },
        processed() {
            return this.batch.synced_count + this.batch.failed_count;
        },
        progressPercent() {
            if (!this.batch.valid_rows) {
                return 0;
            }

            return Math.min(100, Math.round((this.processed / this.batch.valid_rows) * 100));
        },
        canSync() {
            return this.devices.length > 0 && this.batch.valid_rows > 0 && !this.isSyncing;
        },
    },
    watch: {
        'batch.status'(status) {
            if (status === 'syncing') {
                this.startPolling();
            } else {
                this.stopPolling();
            }
        },
    },
    mounted() {
        if (this.isSyncing) {
            this.startPolling();
        }
    },
    beforeUnmount() {
        this.stopPolling();
    },
    methods: {
        startPolling() {
            if (this.pollTimer) {
                return;
            }

            this.pollTimer = setInterval(() => {
                router.reload({
                    only: ['batch', 'users'],
                    preserveScroll: true,
                    preserveState: true,
                });
            }, 2000);
        },
        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
        sync() {
            if (!this.selectedDeviceId || !this.canSync) {
                return;
            }

            router.post(`/import/${this.batch.id}/sync`, { device_id: this.selectedDeviceId }, {
                preserveScroll: true,
                preserveState: true,
            });
        },
        rowStatus(user) {
            if (!user.is_valid) {
                return 'skipped';
            }

            return user.sync_status;
        },
    },
};
</script>

<template>
    <div>
        <div class="mb-6">
            <Link href="/" class="text-sm text-slate-500 hover:text-slate-700">&larr; Imports</Link>
            <div class="mt-2 flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-slate-900">{{ batch.original_filename }}</h1>
                <StatusPill :status="batch.status" />
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ batch.total_rows }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Valid</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ batch.valid_rows }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Invalid</p>
                <p class="mt-1 text-2xl font-semibold text-rose-500">{{ batch.invalid_rows }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Synced</p>
                <p class="mt-1 text-2xl font-semibold text-blue-600">{{ batch.synced_count }}<span v-if="batch.failed_count" class="text-base font-medium text-rose-500"> · {{ batch.failed_count }} failed</span></p>
            </div>
        </div>

        <!-- Sync panel -->
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-700">Target device</label>
                    <select
                        v-model="selectedDeviceId"
                        class="mt-1 w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-50"
                        :disabled="isSyncing || devices.length === 0"
                    >
                        <option v-for="device in devices" :key="device.id" :value="device.id">
                            {{ device.name }} ({{ device.ip_address }})
                        </option>
                    </select>
                    <p v-if="devices.length === 0" class="mt-1 text-xs text-amber-600">
                        <Link href="/devices" class="underline">Add a device</Link> first.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canSync || !selectedDeviceId"
                    @click="sync"
                >
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a7 7 0 0 0-6.32 4.02.75.75 0 0 0 1.35.65A5.5 5.5 0 0 1 15.5 10h-1.75a.5.5 0 0 0-.35.85l2.5 2.5a.5.5 0 0 0 .7 0l2.5-2.5a.5.5 0 0 0-.35-.85H17A7 7 0 0 0 10 3Z" /></svg>
                    {{ isSyncing ? 'Syncing…' : `Sync ${batch.valid_rows} users` }}
                </button>
            </div>

            <div v-if="isSyncing || batch.status === 'completed' || batch.status === 'failed'" class="mt-5">
                <div class="mb-1 flex justify-between text-xs text-slate-500">
                    <span>{{ processed }} of {{ batch.valid_rows }} processed</span>
                    <span>{{ progressPercent }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="batch.failed_count && !isSyncing ? 'bg-amber-500' : 'bg-blue-600'"
                        :style="{ width: progressPercent + '%' }"
                    />
                </div>
            </div>
        </div>

        <!-- Users table -->
        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Row</th>
                            <th class="px-4 py-3">User ID</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">On device</th>
                            <th class="px-4 py-3">PIN</th>
                            <th class="px-4 py-3">Card</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50" :class="{ 'bg-rose-50/40': !user.is_valid }">
                            <td class="px-4 py-3 text-slate-400">{{ user.row_number }}</td>
                            <td class="px-4 py-3 font-mono text-slate-700">{{ user.user_id || '—' }}</td>
                            <td class="px-4 py-3 text-slate-900">{{ user.name }}</td>
                            <td class="px-4 py-3 font-mono text-slate-500">{{ user.name_ascii || '—' }}</td>
                            <td class="px-4 py-3 font-mono text-slate-500">{{ user.password || '—' }}</td>
                            <td class="px-4 py-3 font-mono text-slate-500">{{ user.card_number || '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="user.privilege === 'admin'" class="rounded bg-violet-50 px-1.5 py-0.5 text-xs font-medium text-violet-700">Admin</span>
                                <span v-else class="text-slate-500">User</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1">
                                    <StatusPill :status="rowStatus(user)" />
                                    <span v-if="!user.is_valid && user.validation_errors.length" class="text-xs text-rose-500">
                                        {{ user.validation_errors.join(', ') }}
                                    </span>
                                    <span v-else-if="user.sync_error" class="text-xs text-rose-500">{{ user.sync_error }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="users.last_page > 1" class="flex flex-wrap items-center gap-1 border-t border-slate-100 px-4 py-3">
                <template v-for="(link, index) in users.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        v-html="link.label"
                    />
                    <span v-else class="px-3 py-1.5 text-sm text-slate-300" v-html="link.label" />
                </template>
            </div>
        </div>
    </div>
</template>
