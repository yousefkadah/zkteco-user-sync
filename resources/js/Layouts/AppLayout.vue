<script>
import { Link, router } from '@inertiajs/vue3';

export default {
    name: 'AppLayout',
    components: { Link },
    data() {
        return {
            flash: { message: '', type: 'success' },
            flashTimer: null,
            navigation: [
                { label: 'Imports', href: '/', match: ['/', '/import'] },
                { label: 'Devices', href: '/devices', match: ['/devices'] },
            ],
        };
    },
    computed: {
        appName() {
            return this.$page.props.app?.name ?? 'ZKTeco User Sync';
        },
        appVersion() {
            return this.$page.props.app?.version ?? '';
        },
        currentPath() {
            return this.$page.url.split('?')[0];
        },
    },
    watch: {
        '$page.props.flash': {
            handler(flash) {
                if (flash?.success) {
                    this.showFlash(flash.success, 'success');
                } else if (flash?.error) {
                    this.showFlash(flash.error, 'error');
                }
            },
            deep: true,
            immediate: true,
        },
    },
    methods: {
        isActive(item) {
            if (item.href === '/') {
                return this.currentPath === '/' || this.currentPath.startsWith('/import');
            }

            return item.match.some((path) => this.currentPath.startsWith(path));
        },
        showFlash(message, type) {
            this.flash = { message, type };
            clearTimeout(this.flashTimer);
            this.flashTimer = setTimeout(() => {
                this.flash.message = '';
            }, 5000);
        },
        dismissFlash() {
            this.flash.message = '';
        },
    },
};
</script>

<template>
    <div class="flex min-h-full">
        <aside class="hidden w-60 shrink-0 flex-col border-r border-slate-200 bg-white px-4 py-6 sm:flex">
            <div class="flex items-center gap-2 px-2">
                <div class="flex size-9 items-center justify-center rounded-xl bg-blue-600 text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="14" rx="2" /><path d="M8 21h8M12 18v3" /></svg>
                </div>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-slate-900">ZKTeco</p>
                    <p class="text-xs text-slate-500">User Sync</p>
                </div>
            </div>

            <nav class="mt-8 flex flex-col gap-1">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    class="rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="isActive(item)
                        ? 'bg-blue-50 text-blue-700'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="mt-auto px-2 text-xs text-slate-400">
                v{{ appVersion }}
            </div>
        </aside>

        <div class="flex min-h-full flex-1 flex-col">
            <main class="mx-auto w-full max-w-6xl flex-1 px-6 py-8">
                <slot />
            </main>
        </div>

        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="flash.message"
                class="fixed bottom-6 right-6 z-50 flex max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-lg ring-1"
                :class="flash.type === 'error'
                    ? 'bg-rose-50 text-rose-800 ring-rose-200'
                    : 'bg-emerald-50 text-emerald-800 ring-emerald-200'"
            >
                <span class="flex-1">{{ flash.message }}</span>
                <button type="button" class="text-current/60 hover:text-current" @click="dismissFlash">
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                </button>
            </div>
        </transition>
    </div>
</template>
