<template>
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
        <div class="relative flex-1">
            <input
                type="text"
                :value="search"
                @input="$emit('update:search', $event.target.value)"
                :placeholder="placeholder"
                class="w-full bg-slate-800/60 border border-white/10 rounded-xl px-4 py-2.5 pl-10 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 transition-all"
            />
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button v-if="search" @click="$emit('update:search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <label class="flex items-center gap-2 shrink-0 select-none" style="cursor: pointer">
            <input
                type="checkbox"
                :checked="hideCompleted"
                @change="$emit('update:hideCompleted', $event.target.checked)"
                class="w-4 h-4 rounded border-white/20 bg-slate-800 text-blue-500 focus:ring-blue-500/20 focus:ring-offset-0"
            />
            <span class="text-xs sm:text-sm text-slate-400 font-medium">{{ hideLabel }}</span>
        </label>
    </div>
</template>

<script setup>
defineProps({
    search: { type: String, default: '' },
    hideCompleted: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Rechercher...' },
    hideLabel: { type: String, default: 'Masquer complétés' },
});

defineEmits(['update:search', 'update:hideCompleted']);
</script>
