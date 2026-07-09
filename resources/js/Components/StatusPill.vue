<script>
export default {
    name: 'StatusPill',
    props: {
        status: { type: String, default: '' },
    },
    computed: {
        classes() {
            const map = {
                parsed: 'bg-slate-100 text-slate-700 ring-slate-200',
                pending: 'bg-slate-100 text-slate-600 ring-slate-200',
                syncing: 'bg-blue-50 text-blue-700 ring-blue-200',
                synced: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                failed: 'bg-rose-50 text-rose-700 ring-rose-200',
                skipped: 'bg-amber-50 text-amber-700 ring-amber-200',
            };

            return map[this.status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
        },
        label() {
            if (!this.status) {
                return '—';
            }

            return this.status.charAt(0).toUpperCase() + this.status.slice(1);
        },
    },
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="classes"
    >
        <span
            v-if="status === 'syncing'"
            class="size-1.5 animate-pulse rounded-full bg-blue-500"
        />
        {{ label }}
    </span>
</template>
