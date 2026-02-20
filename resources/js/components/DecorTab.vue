<template>
    <div class="space-y-6">
        <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-violet-600/5 blur-3xl -mr-16 -mt-16"></div>
            <div class="relative z-10 flex justify-between items-end mb-4 sm:mb-6">
                <div>
                    <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                        <div class="w-2 h-6 sm:h-8 bg-violet-500 rounded-full shadow-lg shadow-violet-500/50"></div>
                        Décorations
                    </h3>
                    <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">Collection de décorations du personnage</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl sm:text-3xl font-black text-violet-400 font-mono">{{ character.decorCount }}</div>
                    <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">/ {{ character.decor?.length || 0 }} total</div>
                </div>
            </div>
        </div>

        <SearchFilter
            v-model:search="search"
            v-model:hideCompleted="hideCompleted"
            placeholder="Rechercher une décoration..."
            hideLabel="Masquer obtenues"
        />

        <div class="flex justify-between items-center">
            <h4 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                Détail des Décorations
                <div class="flex-1 h-px bg-violet-500/10"></div>
            </h4>
            <div v-if="totalPages > 1" class="flex items-center gap-2 ml-4">
                <button @click="page--" :disabled="page === 1" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                    <span class="text-xs text-slate-300">&larr;</span>
                </button>
                <span class="text-xs sm:text-sm font-mono text-slate-400">{{ page }} / {{ totalPages }}</span>
                <button @click="page++" :disabled="page === totalPages" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                    <span class="text-xs text-slate-300">&rarr;</span>
                </button>
            </div>
        </div>

        <div v-if="filteredDecor.length === 0" class="text-center py-8 text-slate-500 text-sm">
            Aucun résultat trouvé.
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <a v-for="item in paginatedDecor" :key="item.id" :href="item.item_id ? `https://www.wowhead.com/fr/item=${item.item_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(item.name)}`" target="_blank" rel="noopener" class="flex items-center gap-3 p-3 sm:p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-violet-500/30 transition-all">
                <img v-if="item.icon_url" :src="item.icon_url" :alt="item.name" class="w-10 h-10 rounded-lg border border-white/10 group-hover:scale-110 transition-transform shadow-lg shadow-violet-500/5 object-cover" loading="lazy" />
                <div v-else class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-violet-500 font-bold border border-white/10 group-hover:scale-110 transition-transform shadow-lg shadow-violet-500/5">
                    D
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm md:text-base font-bold text-slate-200 group-hover:text-violet-400 transition-colors truncate">{{ item.name }}</div>
                    <div class="text-[10px] sm:text-xs text-slate-500 font-mono">ID: {{ item.id }}</div>
                </div>
                <div v-if="item.is_completed" class="px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black uppercase bg-green-500/10 text-green-400 border border-green-500/20 shrink-0">
                    Obtenue
                </div>
            </a>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import SearchFilter from './SearchFilter.vue';

const props = defineProps({
    character: { type: Object, required: true },
});

const page = ref(1);
const itemsPerPage = 24;
const search = ref('');
const hideCompleted = ref(false);

const sortedDecor = computed(() => {
    const decor = props.character.decor || [];
    return [...decor].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const filteredDecor = computed(() => {
    let result = sortedDecor.value;
    if (search.value) {
        const q = search.value.toLowerCase();
        result = result.filter(d => d.name.toLowerCase().includes(q));
    }
    if (hideCompleted.value) {
        result = result.filter(d => !d.is_completed);
    }
    return result;
});

const paginatedDecor = computed(() => {
    const start = (page.value - 1) * itemsPerPage;
    return filteredDecor.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil(filteredDecor.value.length / itemsPerPage));

watch([search, hideCompleted], () => { page.value = 1; });
</script>
