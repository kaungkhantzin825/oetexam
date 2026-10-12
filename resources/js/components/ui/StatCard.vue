<script setup>
const props = defineProps({
    icon: { type: [Object, Function], default: null },
    label: { type: String, default: '' },
    value: { type: [String, Number], default: '' },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'blue' }, // blue | teal | amber | purple | slate
});

const tones = {
    blue: 'bg-primary-50 text-primary-600',
    teal: 'bg-teal-50 text-teal-600',
    amber: 'bg-amber-50 text-amber-600',
    purple: 'bg-purple-50 text-purple-600',
    slate: 'bg-slate-100 text-slate-600',
};
</script>

<template>
    <div class="card card-pad">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">{{ label }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">{{ value }}</p>
                <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
            </div>
            <div
                v-if="props.icon"
                :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-lg', tones[tone] || tones.blue]"
            >
                <component :is="props.icon" :size="20" />
            </div>
        </div>
    </div>
</template>
