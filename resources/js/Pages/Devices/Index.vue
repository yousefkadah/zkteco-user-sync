<script>
import { router } from '@inertiajs/vue3';
import Modal from '../../Components/Modal.vue';

export default {
    name: 'DevicesIndex',
    components: { Modal },
    props: {
        devices: { type: Array, default: () => [] },
    },
    data() {
        return {
            showModal: false,
            editingId: null,
            testingId: null,
            processing: false,
            errors: {},
            form: this.emptyForm(),
        };
    },
    computed: {
        modalTitle() {
            return this.editingId ? 'Edit device' : 'Add device';
        },
    },
    methods: {
        emptyForm() {
            return { name: '', ip_address: '', port: 4370, comm_key: '', notes: '' };
        },
        openCreate() {
            this.editingId = null;
            this.form = this.emptyForm();
            this.errors = {};
            this.showModal = true;
        },
        openEdit(device) {
            this.editingId = device.id;
            this.form = {
                name: device.name,
                ip_address: device.ip_address,
                port: device.port,
                comm_key: device.comm_key ?? '',
                notes: device.notes ?? '',
            };
            this.errors = {};
            this.showModal = true;
        },
        submit() {
            const options = {
                preserveScroll: true,
                onStart: () => {
                    this.processing = true;
                },
                onFinish: () => {
                    this.processing = false;
                },
                onError: (errors) => {
                    this.errors = errors;
                },
                onSuccess: () => {
                    this.showModal = false;
                },
            };

            if (this.editingId) {
                router.put(`/devices/${this.editingId}`, this.form, options);
            } else {
                router.post('/devices', this.form, options);
            }
        },
        remove(device) {
            if (!window.confirm(`Remove device "${device.name}"?`)) {
                return;
            }

            router.delete(`/devices/${device.id}`, { preserveScroll: true });
        },
        test(device) {
            this.testingId = device.id;
            router.post(`/devices/${device.id}/test`, {}, {
                preserveScroll: true,
                onFinish: () => {
                    this.testingId = null;
                },
            });
        },
        formatDate(iso) {
            return iso ? new Date(iso).toLocaleString() : 'Never';
        },
    },
};
</script>

<template>
    <div>
        <header class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Devices</h1>
                <p class="mt-1 text-sm text-slate-500">
                    ZKTeco terminals reachable on your local network (default port 4370).
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                @click="openCreate"
            >
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 5a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-3v3a1 1 0 1 1-2 0v-3H6a1 1 0 1 1 0-2h3V6a1 1 0 0 1 1-1Z" /></svg>
                Add device
            </button>
        </header>

        <div v-if="devices.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-sm text-slate-500">
            No devices yet. Add your first ZKTeco terminal to start syncing.
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2">
            <div
                v-for="device in devices"
                :key="device.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ device.name }}</h3>
                        <p class="mt-0.5 font-mono text-sm text-slate-500">{{ device.ip_address }}:{{ device.port }}</p>
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                        :class="device.last_connection_ok
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                            : 'bg-slate-100 text-slate-500 ring-slate-200'"
                    >
                        <span class="size-1.5 rounded-full" :class="device.last_connection_ok ? 'bg-emerald-500' : 'bg-slate-400'" />
                        {{ device.last_connection_ok ? 'Online' : 'Unknown' }}
                    </span>
                </div>

                <dl class="mt-4 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Serial</dt>
                        <dd class="text-slate-600">{{ device.serial_number ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Comm key</dt>
                        <dd class="text-slate-600">{{ device.comm_key ?? 'None' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Last checked</dt>
                        <dd class="text-slate-600">{{ formatDate(device.last_connected_at) }}</dd>
                    </div>
                </dl>

                <div class="mt-5 flex gap-2">
                    <button
                        type="button"
                        class="inline-flex flex-1 items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        :disabled="testingId === device.id"
                        @click="test(device)"
                    >
                        {{ testingId === device.id ? 'Testing…' : 'Test connection' }}
                    </button>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50" @click="openEdit(device)">Edit</button>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50" @click="remove(device)">Delete</button>
                </div>
            </div>
        </div>

        <Modal :show="showModal" :title="modalTitle" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Name</label>
                    <input v-model="form.name" type="text" placeholder="Front door terminal" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <p v-if="errors.name" class="mt-1 text-xs text-rose-600">{{ errors.name }}</p>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-700">IP address</label>
                        <input v-model="form.ip_address" type="text" placeholder="192.168.1.201" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm focus:border-blue-500 focus:ring-blue-500">
                        <p v-if="errors.ip_address" class="mt-1 text-xs text-rose-600">{{ errors.ip_address }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Port</label>
                        <input v-model.number="form.port" type="number" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <p v-if="errors.port" class="mt-1 text-xs text-rose-600">{{ errors.port }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Communication key <span class="text-slate-400">(optional)</span></label>
                    <input v-model="form.comm_key" type="number" placeholder="Leave blank if the device has none" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <p v-if="errors.comm_key" class="mt-1 text-xs text-rose-600">{{ errors.comm_key }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Notes <span class="text-slate-400">(optional)</span></label>
                    <textarea v-model="form.notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100" @click="showModal = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50" :disabled="processing">
                        {{ processing ? 'Saving…' : 'Save device' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
