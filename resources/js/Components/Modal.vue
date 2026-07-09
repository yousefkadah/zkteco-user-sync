<script>
export default {
    name: 'Modal',
    props: {
        show: { type: Boolean, default: false },
        title: { type: String, default: '' },
    },
    emits: ['close'],
    watch: {
        show(value) {
            document.body.style.overflow = value ? 'hidden' : '';
        },
    },
    beforeUnmount() {
        document.body.style.overflow = '';
    },
};
</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 pt-16 backdrop-blur-sm"
                @click.self="$emit('close')"
            >
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                        <button
                            type="button"
                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            @click="$emit('close')"
                        >
                            <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                        </button>
                    </div>
                    <div class="px-6 py-5">
                        <slot />
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
