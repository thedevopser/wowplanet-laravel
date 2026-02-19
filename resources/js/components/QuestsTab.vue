<template>
    <div class="space-y-6">
        <ExpansionSelector
            :expansions="store.expansions"
            :activeExpansion="activeExpansion"
            :collections="store.character.collections"
            collectionType="quests"
            activeColor="blue"
            @update:activeExpansion="activeExpansion = $event"
        />

        <div v-if="currentCollection" class="space-y-8">
            <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/5 blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10">
                    <div class="flex justify-between items-end mb-4 sm:mb-6">
                        <div>
                            <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                                <div class="w-2 h-6 sm:h-8 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50"></div>
                                Quêtes
                            </h3>
                            <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">Progression globale de l'extension</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl sm:text-3xl font-black text-blue-400 font-mono">
                                {{ Math.round(currentCollection.quests.completed / (currentCollection.quests.total || 1) * 100) }}%
                            </div>
                            <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">
                                {{ currentCollection.quests.completed }} / {{ currentCollection.quests.total }}
                            </div>
                        </div>
                    </div>
                    <div class="h-3 bg-slate-800 rounded-full overflow-hidden border border-white/5">
                        <div class="h-full bg-linear-to-r from-blue-700 via-blue-500 to-blue-400 transition-all duration-1000 relative shadow-[0_0_15px_rgba(59,130,246,0.3)]" :style="{ width: progressPercent + '%' }">
                            <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                        </div>
                    </div>
                </div>
            </div>

            <SearchFilter
                v-model:search="search"
                v-model:hideCompleted="hideCompleted"
                placeholder="Rechercher une quête..."
                hideLabel="Masquer complétées"
            />

            <!-- Zones Drill-down -->
            <section v-if="filteredZones.length">
                <div class="flex justify-between items-center mb-4 sm:mb-6">
                    <h4 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                        Décomposition par zone
                        <div class="flex-1 h-px bg-slate-700"></div>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-start">
                    <div
                        v-for="zone in paginatedZones"
                        :key="zone.name"
                        @click="toggleZone(zone)"
                        class="bg-slate-800/40 border border-white/5 p-4 rounded-2xl hover:bg-slate-800/60 transition-colors group cursor-pointer"
                    >
                        <div class="flex justify-between items-start mb-3">
                            <span class="text-sm md:text-base font-bold text-slate-300 group-hover:text-blue-400 transition-colors">{{ zone.name }}</span>
                            <span class="text-[10px] sm:text-xs font-mono text-slate-500">{{ zone.completed }}/{{ zone.total }}</span>
                        </div>
                        <div class="h-1 bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-600/80 transition-all duration-700" :style="{ width: (zone.completed / zone.total * 100) + '%' }"></div>
                        </div>
                        <div v-if="expandedZone === zone.name" class="mt-4 pt-4 border-t border-white/5 space-y-1 max-h-48 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                            <div v-for="item in sortedItems(zone.items)" :key="item.id" class="flex justify-between items-center text-[10px] sm:text-xs py-1">
                                <a :href="`https://www.wowhead.com/fr/quest=${item.id}`" target="_blank" rel="noopener" @click.stop :class="[item.is_completed ? 'text-blue-400 font-medium' : 'text-slate-500', 'hover:underline']">{{ item.name }}</a>
                                <span v-if="item.is_completed" class="text-green-500 font-bold">&check;</span>
                                <span v-else class="text-slate-800">&cir;</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <div v-else-if="search || hideCompleted" class="text-center py-8 text-slate-500 text-sm">
                Aucun résultat trouvé.
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useCharacterStore } from '../stores/character';
import ExpansionSelector from './ExpansionSelector.vue';
import SearchFilter from './SearchFilter.vue';

const store = useCharacterStore();

const activeExpansion = ref(10);
const expandedZone = ref(null);
const page = ref(1);
const itemsPerPage = 8;
const search = ref('');
const hideCompleted = ref(false);

const currentCollection = computed(() => store.character?.collections?.[activeExpansion.value] || null);

const progressPercent = computed(() => {
    if (!currentCollection.value) return 0;
    const { completed, total } = currentCollection.value.quests;
    return total > 0 ? completed / total * 100 : 0;
});

const sortedZones = computed(() => {
    if (!currentCollection.value) return [];
    const zones = currentCollection.value.quests.zones || [];
    return [...zones].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const filteredZones = computed(() => {
    if (!search.value && !hideCompleted.value) return sortedZones.value;

    const q = search.value.toLowerCase();
    return sortedZones.value.map(zone => {
        let items = zone.items;
        if (search.value) {
            items = items.filter(i => i.name.toLowerCase().includes(q));
        }
        if (hideCompleted.value) {
            items = items.filter(i => !i.is_completed);
        }
        return { ...zone, items, total: items.length, completed: items.filter(i => i.is_completed).length };
    }).filter(zone => zone.items.length > 0);
});

const paginatedZones = computed(() => {
    const start = (page.value - 1) * itemsPerPage;
    return filteredZones.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil(filteredZones.value.length / itemsPerPage));

const toggleZone = (zone) => {
    expandedZone.value = expandedZone.value === zone.name ? null : zone.name;
};

const sortedItems = (items) => [...items].sort((a, b) => a.name.localeCompare(b.name, 'fr'));

watch(activeExpansion, () => {
    page.value = 1;
    expandedZone.value = null;
});

watch([search, hideCompleted], () => {
    page.value = 1;
    expandedZone.value = null;
});
</script>
