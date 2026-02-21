<template>
    <div class="space-y-6">
        <ExpansionSelector
            :expansions="store.expansions"
            :activeExpansion="activeExpansion"
            :collections="store.character.collections"
            collectionType="reputations"
            activeColor="purple"
            @update:activeExpansion="activeExpansion = $event"
        />

        <div v-if="currentCollection" class="space-y-8">
            <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-purple-600/5 blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10">
                    <div class="flex justify-between items-end mb-4 sm:mb-6">
                        <div>
                            <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                                <div class="w-2 h-6 sm:h-8 bg-purple-500 rounded-full shadow-lg shadow-purple-500/50"></div>
                                Réputations
                            </h3>
                            <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">Progression auprès des factions</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl sm:text-3xl font-black text-purple-400 font-mono">
                                {{ currentCollection.reputations.completed }}
                            </div>
                            <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">
                                {{ currentCollection.reputations.completed }} / {{ currentCollection.reputations.total }} terminé
                            </div>
                        </div>
                    </div>
                    <div class="h-3 bg-slate-800 rounded-full overflow-hidden border border-white/5">
                        <div class="h-full bg-linear-to-r from-purple-700 via-purple-500 to-purple-400 transition-all duration-1000 relative shadow-[0_0_15px_rgba(168,85,247,0.3)]" :style="{ width: progressPercent + '%' }">
                            <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                        </div>
                    </div>
                </div>
            </div>

            <SearchFilter
                v-model:search="search"
                v-model:hideCompleted="hideCompleted"
                placeholder="Rechercher une faction..."
                hideLabel="Masquer terminées"
            />

            <!-- Factions Grid -->
            <section v-if="filteredFactions.length">
                <div class="flex justify-between items-center mb-4 sm:mb-6">
                    <h4 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                        Factions
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
                        v-for="faction in paginatedFactions"
                        :key="faction.id"
                        class="bg-slate-800/40 border border-white/5 p-4 rounded-2xl hover:bg-slate-800/60 transition-colors group"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <a
                                :href="`https://www.wowhead.com/fr/faction=${faction.id}`"
                                target="_blank"
                                rel="noopener"
                                class="text-sm md:text-base font-bold text-slate-300 group-hover:text-purple-400 transition-colors hover:underline"
                            >{{ faction.name }}</a>
                            <span
                                :class="[
                                    'text-[10px] sm:text-xs font-bold px-2 py-0.5 rounded-full border whitespace-nowrap ml-2',
                                    standingClasses(faction)
                                ]"
                            >{{ faction.standing_name }}</span>
                        </div>
                        <template v-if="faction.max > 0 && faction.value > 0">
                            <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden mb-1.5">
                                <div
                                    :class="['h-full transition-all duration-700 rounded-full', standingBarColor(faction)]"
                                    :style="{ width: factionProgress(faction) + '%' }"
                                ></div>
                            </div>
                            <div class="text-[10px] sm:text-xs font-mono text-slate-500 text-right">
                                {{ faction.value.toLocaleString('fr-FR') }} / {{ faction.max.toLocaleString('fr-FR') }}
                            </div>
                        </template>
                    </div>
                </div>
            </section>
            <div v-else-if="currentCollection.reputations.total === 0" class="text-center py-8 text-slate-500 text-sm">
                Aucune réputation pour cette extension.
            </div>
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
const page = ref(1);
const itemsPerPage = 12;
const search = ref('');
const hideCompleted = ref(false);

const currentCollection = computed(() => store.character?.collections?.[activeExpansion.value] || null);

const progressPercent = computed(() => {
    if (!currentCollection.value) return 0;
    const { completed, total } = currentCollection.value.reputations;
    return total > 0 ? completed / total * 100 : 0;
});

const sortedFactions = computed(() => {
    if (!currentCollection.value) return [];
    const factions = currentCollection.value.reputations.factions || [];
    return [...factions].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const filteredFactions = computed(() => {
    let factions = sortedFactions.value;

    if (search.value) {
        const q = search.value.toLowerCase();
        factions = factions.filter(f => f.name.toLowerCase().includes(q));
    }
    if (hideCompleted.value) {
        factions = factions.filter(f => !f.completed);
    }

    return factions;
});

const paginatedFactions = computed(() => {
    const start = (page.value - 1) * itemsPerPage;
    return filteredFactions.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil(filteredFactions.value.length / itemsPerPage));

const factionProgress = (faction) => {
    return faction.max > 0 ? (faction.value / faction.max * 100) : 0;
};

const standingClasses = (faction) => {
    // Renown factions: amber for completed, sky for in-progress
    if (faction.renown_level > 0) {
        return faction.completed
            ? 'text-amber-300 bg-amber-300/10 border-amber-300/30'
            : 'text-sky-400 bg-sky-400/10 border-sky-400/30';
    }

    const map = {
        0: 'text-red-500 bg-red-500/10 border-red-500/30',
        1: 'text-red-400 bg-red-400/10 border-red-400/30',
        2: 'text-orange-400 bg-orange-400/10 border-orange-400/30',
        3: 'text-yellow-400 bg-yellow-400/10 border-yellow-400/30',
        4: 'text-green-400 bg-green-400/10 border-green-400/30',
        5: 'text-teal-400 bg-teal-400/10 border-teal-400/30',
        6: 'text-purple-400 bg-purple-400/10 border-purple-400/30',
        7: 'text-amber-300 bg-amber-300/10 border-amber-300/30',
    };
    return map[faction.tier] || 'text-sky-400 bg-sky-400/10 border-sky-400/30';
};

const standingBarColor = (faction) => {
    if (faction.renown_level > 0) {
        return 'bg-sky-400';
    }

    const map = {
        0: 'bg-red-500',
        1: 'bg-red-400',
        2: 'bg-orange-400',
        3: 'bg-yellow-400',
        4: 'bg-green-400',
        5: 'bg-teal-400',
        6: 'bg-purple-400',
        7: 'bg-amber-300',
    };
    return map[faction.tier] || 'bg-sky-400';
};

watch(activeExpansion, () => {
    page.value = 1;
});

watch([search, hideCompleted], () => {
    page.value = 1;
});
</script>
