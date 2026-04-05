<template>
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
        <div class="relative flex-1">
            <input
                type="text"
                :value="search"
                @input="onInput"
                :placeholder="placeholder"
                :aria-label="placeholder"
                class="w-full bg-slate-800/60 border border-white/10 rounded-xl px-4 py-2.5 pl-10 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 transition-all"
            />
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button v-if="search" @click="clear" aria-label="Effacer la recherche" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div v-if="showHideToggle || $slots['extra-toggles']" class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
            <button
                v-if="showHideToggle"
                type="button"
                @click="$emit('update:hideCompleted', !hideCompleted)"
                :class="[
                    'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium border transition-all select-none',
                    hideCompleted
                        ? 'bg-blue-500/15 border-blue-500/30 text-blue-400'
                        : 'bg-slate-800/60 border-white/10 text-slate-500 hover:text-slate-300 hover:border-white/20'
                ]"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="hideCompleted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path v-if="!hideCompleted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                {{ hideLabel }}
            </button>
            <slot name="extra-toggles"></slot>
        </div>
    </div>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue';

const props = defineProps({
    search: { type: String, default: '' },
    hideCompleted: { type: Boolean, default: false },
    showHideToggle: { type: Boolean, default: true },
    placeholder: { type: String, default: 'Rechercher...' },
    hideLabel: { type: String, default: 'Masquer complétés' },
    debounceMs: { type: Number, default: 0 },
});

const emit = defineEmits(['update:search', 'update:hideCompleted', 'search-debounced']);

let debounceTimer = null;

function onInput(event) {
    const value = event.target.value;
    emit('update:search', value);

    if (props.debounceMs > 0) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            emit('search-debounced', value);
        }, props.debounceMs);
    }
}

function clear() {
    emit('update:search', '');
    if (props.debounceMs > 0) {
        clearTimeout(debounceTimer);
        emit('search-debounced', '');
    }
}

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
});
</script>
