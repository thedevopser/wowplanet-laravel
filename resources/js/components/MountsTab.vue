<template>
    <div class="space-y-6">
        <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-amber-600/5 blur-3xl -mr-16 -mt-16"></div>
            <div class="relative z-10 flex justify-between items-end mb-4 sm:mb-6">
                <div>
                    <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                        <div class="w-2 h-6 sm:h-8 bg-amber-500 rounded-full shadow-lg shadow-amber-500/50"></div>
                        Montures
                    </h3>
                    <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">Collection de montures du personnage</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl sm:text-3xl font-black text-amber-400 font-mono">{{ character.mountsCount }}</div>
                    <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">/ {{ character.mounts?.length || 0 }} total</div>
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center">
            <h4 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                Détail des Montures
                <div class="flex-1 h-px bg-amber-500/10"></div>
            </h4>
            <div v-if="totalPages > 1" class="flex items-center gap-2 ml-4">
                <button @click="page--" :disabled="page === 1" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                    <span class="text-xs text-slate-300">&larr;</span>
                </button>
                <span class="text-[10px] font-mono text-slate-500">{{ page }} / {{ totalPages }}</span>
                <button @click="page++" :disabled="page === totalPages" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                    <span class="text-xs text-slate-300">&rarr;</span>
                </button>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <a v-for="mount in paginatedMounts" :key="mount.id" :href="`https://www.wowhead.com/fr/mount=${mount.id}`" target="_blank" rel="noopener" class="flex items-center gap-3 p-3 sm:p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-amber-500/30 transition-all">
                <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-amber-500 font-bold border border-white/10 group-hover:scale-110 transition-transform shadow-lg shadow-amber-500/5">
                    M
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm md:text-base font-bold text-slate-200 group-hover:text-amber-400 transition-colors truncate">{{ mount.name }}</div>
                    <div class="text-[10px] sm:text-xs text-slate-500 font-mono">ID: {{ mount.id }}</div>
                </div>
                <div v-if="mount.is_completed" class="px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black uppercase bg-green-500/10 text-green-400 border border-green-500/20 shrink-0">
                    Obtenue
                </div>
            </a>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    character: { type: Object, required: true },
});

const page = ref(1);
const itemsPerPage = 24;

const sortedMounts = computed(() => {
    const mounts = props.character.mounts || [];
    return [...mounts].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const paginatedMounts = computed(() => {
    const start = (page.value - 1) * itemsPerPage;
    return sortedMounts.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil(sortedMounts.value.length / itemsPerPage));
</script>
