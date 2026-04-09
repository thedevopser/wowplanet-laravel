<template>
    <a
        v-if="activeEntry"
        :href="`https://www.wowhead.com/fr/spell=${activeEntry.spell_id}`"
        target="_blank"
        rel="noopener"
        :class="[
            'relative block border-2 transition-all overflow-hidden',
            isSelected
                ? 'border-yellow-500 shadow-lg shadow-yellow-500/30'
                : 'border-slate-600/40 opacity-40',
            node.type === 'choice' ? 'rounded-full' : 'rounded-md',
        ]"
        :style="{ width: size + 'px', height: size + 'px' }"
    >
        <img
            v-if="iconUrl && !iconErrored"
            :src="iconUrl"
            :alt="activeEntry.name"
            class="absolute inset-0 w-full h-full object-cover"
            loading="lazy"
            @error="iconErrored = true"
        />
        <div
            v-else
            class="absolute inset-0 bg-slate-700"
        ></div>

        <!-- Rank badge -->
        <div
            v-if="node.max_rank > 1"
            :class="[
                'absolute -bottom-1 -right-1 text-[10px] font-bold font-mono leading-none px-1 py-0.5 rounded border z-10',
                isSelected
                    ? 'bg-yellow-900/95 border-yellow-500/60 text-yellow-300'
                    : 'bg-slate-900 border-slate-600/50 text-slate-500',
            ]"
        >{{ node.selected_rank }}/{{ node.max_rank }}</div>
    </a>
    <div
        v-else
        :class="[
            'border-2 border-slate-600/40 bg-slate-800/30 opacity-40',
            node.type === 'choice' ? 'rounded-full' : 'rounded-md',
        ]"
        :style="{ width: size + 'px', height: size + 'px' }"
    ></div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    node: { type: Object, required: true },
    size: { type: Number, default: 56 },
});

const iconErrored = ref(false);

const isSelected = computed(() => props.node.selected_rank > 0);

const activeEntry = computed(() => {
    if (!props.node.entries || props.node.entries.length === 0) return null;

    if (props.node.type === 'choice') {
        const selected = props.node.entries.find(e => e.selected);
        return selected || props.node.entries[0];
    }

    return props.node.entries[0];
});

const iconUrl = computed(() => activeEntry.value?.icon_url || null);
</script>
