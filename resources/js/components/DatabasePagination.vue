<template>
    <div v-if="lastPage > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4">
        <div class="text-xs text-slate-500 font-mono">
            {{ total.toLocaleString('fr-FR') }} résultats — page {{ currentPage }} / {{ lastPage }}
        </div>
        <div class="flex items-center gap-1">
            <button
                @click="$emit('page-change', currentPage - 1)"
                :disabled="currentPage === 1"
                class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors"
            >
                <span class="text-xs text-slate-300">&larr;</span>
            </button>

            <template v-for="p in visiblePages" :key="p">
                <span v-if="p === '...'" class="px-1 text-xs text-slate-600">...</span>
                <button
                    v-else
                    @click="$emit('page-change', p)"
                    :class="[
                        'w-8 h-8 rounded-lg border text-xs font-mono transition-colors',
                        p === currentPage
                            ? 'bg-slate-700 border-white/10 text-white'
                            : 'border-white/5 text-slate-400 hover:bg-slate-800 hover:text-white'
                    ]"
                >{{ p }}</button>
            </template>

            <button
                @click="$emit('page-change', currentPage + 1)"
                :disabled="currentPage === lastPage"
                class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors"
            >
                <span class="text-xs text-slate-300">&rarr;</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    total: { type: Number, default: 0 },
});

defineEmits(['page-change']);

const visiblePages = computed(() => {
    const pages = [];
    const total = props.lastPage;
    const current = props.currentPage;

    if (total <= 7) {
        for (let i = 1; i <= total; i++) pages.push(i);
        return pages;
    }

    pages.push(1);

    if (current > 3) pages.push('...');

    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);

    for (let i = start; i <= end; i++) pages.push(i);

    if (current < total - 2) pages.push('...');

    pages.push(total);

    return pages;
});
</script>
