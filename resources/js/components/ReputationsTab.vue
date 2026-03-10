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
            >
                <template #extra-toggles>
                    <button
                        type="button"
                        @click="hideUnstarted = !hideUnstarted"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium border transition-all select-none',
                            hideUnstarted
                                ? 'bg-blue-500/15 border-blue-500/30 text-blue-400'
                                : 'bg-slate-800/60 border-white/10 text-slate-500 hover:text-slate-300 hover:border-white/20'
                        ]"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path v-if="hideUnstarted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path v-if="!hideUnstarted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Masquer non commencées
                    </button>
                </template>
            </SearchFilter>

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
                        :class="[
                            'border p-4 rounded-2xl transition-colors group',
                            faction.started === false
                                ? 'bg-slate-900/30 border-slate-700/30 opacity-60'
                                : 'bg-slate-800/40 border-white/5 hover:bg-slate-800/60'
                        ]"
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
                        <div v-if="betterCharacter(faction)" class="mt-2 flex items-center gap-1.5 text-[10px] text-amber-400/70">
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <span>Meilleur : <strong class="text-amber-300">{{ betterCharacter(faction).character_name }}</strong> &mdash; {{ betterCharacter(faction).standing_name }}</span>
                        </div>
                    </div>
                </div>
            </section>
            <div v-else-if="currentCollection.reputations.total === 0" class="text-center py-8 text-slate-500 text-sm">
                Aucune réputation pour cette extension.
            </div>
            <div v-else-if="search || hideCompleted || hideUnstarted" class="text-center py-8 text-slate-500 text-sm">
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

const activeExpansion = ref(store.latestExpansionId);
const page = ref(1);
const itemsPerPage = 12;
const search = ref('');
const hideCompleted = ref(false);
const hideUnstarted = ref(false);

const currentCollection = computed(() => store.character?.collections?.[activeExpansion.value] || null);

const progressPercent = computed(() => {
    if (!currentCollection.value) return 0;
    const { completed, total } = currentCollection.value.reputations;
    return total > 0 ? completed / total * 100 : 0;
});

const sortedFactions = computed(() => {
    if (!currentCollection.value) return [];
    const factions = currentCollection.value.reputations.factions || [];
    return [...factions].sort((a, b) => {
        if (a.started !== b.started) return a.started ? -1 : 1;
        if (a.started && b.started && a.completed !== b.completed) return a.completed ? 1 : -1;
        return a.name.localeCompare(b.name, 'fr');
    });
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
    if (hideUnstarted.value) {
        factions = factions.filter(f => f.started !== false);
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
    if (faction.started === false) {
        return 'text-slate-500 bg-slate-500/10 border-slate-500/30';
    }

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

const betterCharacter = (faction) => {
    const best = store.getBestFactionStanding(faction.id);
    if (!best) return null;
    if (best.character_name === store.character?.name) return null;

    if (faction.renown_level > 0 && best.renown_level <= (faction.renown_level || 0)) return null;
    if (faction.renown_level <= 0 && best.raw <= (faction.raw || 0)) return null;

    return best;
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

watch([search, hideCompleted, hideUnstarted], () => {
    page.value = 1;
});
</script>
