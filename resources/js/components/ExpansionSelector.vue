<template>
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-3">
        <button
            v-for="exp in expansions"
            :key="exp.id"
            @click="$emit('update:activeExpansion', exp.id)"
            :class="[
                'px-3 sm:px-4 py-2 sm:py-3 rounded-xl sm:rounded-2xl text-[11px] sm:text-[13px] font-bold transition-all border flex flex-col items-center gap-1 group relative overflow-hidden',
                activeExpansion === exp.id
                    ? `${activeClasses} scale-105 z-10`
                    : 'bg-slate-800/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-700 hover:border-white/10'
            ]"
        >
            <span class="relative z-10">{{ exp.name }}</span>
            <div v-if="collections[exp.id]" :class="[
                'text-[9px] font-mono px-2 py-0.5 rounded-full border relative z-10',
                activeExpansion === exp.id ? activeBadgeClasses : 'bg-slate-800 border-white/5 opacity-60'
            ]">
                {{ collections[exp.id][collectionType].completed }} / {{ collections[exp.id][collectionType].total }}
            </div>
            <div v-if="collections[exp.id]" class="absolute bottom-0 left-0 h-0.5 bg-white/20 transition-all duration-500" :style="{ width: progressWidth(exp.id) }"></div>
        </button>
    </div>
</template>

<script setup>
const props = defineProps({
    expansions: { type: Array, required: true },
    activeExpansion: { type: Number, required: true },
    collections: { type: Object, required: true },
    collectionType: { type: String, required: true, validator: v => ['quests', 'achievements'].includes(v) },
    activeColor: { type: String, default: 'blue', validator: v => ['blue', 'amber'].includes(v) },
});

defineEmits(['update:activeExpansion']);

const activeClasses = props.activeColor === 'blue'
    ? 'bg-blue-600 border-blue-400 text-white shadow-xl shadow-blue-500/20'
    : 'bg-amber-600 border-amber-400 text-white shadow-xl shadow-amber-500/20';

const activeBadgeClasses = props.activeColor === 'blue'
    ? 'bg-blue-700/50 border-white/20'
    : 'bg-amber-700/50 border-white/20';

const progressWidth = (expId) => {
    const col = props.collections[expId];
    if (!col) return '0%';
    const data = col[props.collectionType];
    return data.total > 0 ? (data.completed / data.total * 100) + '%' : '0%';
};
</script>
