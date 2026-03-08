<template>
    <div class="space-y-6">
        <!-- Empty state -->
        <div v-if="!professions.length" class="card-glass rounded-2xl sm:rounded-3xl border p-8 text-center">
            <p class="text-slate-500 text-sm">Ce personnage n'a aucun m&eacute;tier.</p>
        </div>

        <template v-else>
            <!-- Profession Selector -->
            <div class="flex flex-wrap gap-2 sm:gap-3">
                <button
                    v-for="prof in professions"
                    :key="prof.profession_id"
                    @click="selectProfession(prof.profession_id)"
                    :class="[
                        'px-4 py-2 rounded-xl text-sm font-bold transition-all border',
                        selectedProfessionId === prof.profession_id
                            ? 'bg-emerald-600 border-emerald-400 text-white shadow-lg shadow-emerald-500/20'
                            : 'bg-slate-800/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-700 hover:border-white/10'
                    ]"
                >
                    {{ prof.profession_name }}
                    <span v-if="prof.type === 'secondary'" class="ml-1 text-[10px] font-mono opacity-60">(sec.)</span>
                </button>
            </div>

            <!-- Expansion Selector (not for Archaeology — global skill only) -->
            <ExpansionSelector
                v-if="selectedProfession && !isArchaeology"
                :expansions="store.expansions"
                :activeExpansion="activeExpansion"
                :collections="professionCollections"
                collectionType="recipes"
                activeColor="emerald"
                @update:activeExpansion="activeExpansion = $event"
            />

            <!-- ==================== ARCHAEOLOGY MODE ==================== -->
            <template v-if="isArchaeology">
                <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-cyan-600/5 blur-3xl -mr-16 -mt-16"></div>
                    <div class="relative z-10">
                        <div class="flex justify-between items-end mb-4 sm:mb-6">
                            <div>
                                <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                                    <div class="w-2 h-6 sm:h-8 bg-cyan-500 rounded-full shadow-lg shadow-cyan-500/50"></div>
                                    {{ selectedProfession.profession_name }}
                                </h3>
                                <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">Niveau de comp&eacute;tence global</p>
                            </div>
                            <div v-if="archaeologyMaxPoints > 0" class="text-right">
                                <div class="text-2xl sm:text-3xl font-black text-cyan-400 font-mono">
                                    {{ Math.round(archaeologyPoints / archaeologyMaxPoints * 100) }}%
                                </div>
                                <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">
                                    {{ archaeologyPoints }} / {{ archaeologyMaxPoints }} points
                                </div>
                            </div>
                        </div>
                        <div v-if="archaeologyMaxPoints > 0" class="h-3 bg-slate-800 rounded-full overflow-hidden border border-white/5">
                            <div class="h-full bg-linear-to-r from-cyan-700 via-cyan-500 to-cyan-400 transition-all duration-1000 relative shadow-[0_0_15px_rgba(6,182,212,0.3)]" :style="{ width: (archaeologyPoints / archaeologyMaxPoints * 100) + '%' }">
                                <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                            </div>
                        </div>
                        <div v-else class="text-center py-4">
                            <p class="text-slate-500 text-sm">Aucune donn&eacute;e d'arch&eacute;ologie disponible.</p>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ==================== STANDARD PROFESSION MODE ==================== -->
            <template v-else>
                <!-- Tier NOT learned: simplified display -->
                <template v-if="currentExpansionData && !currentExpansionData.has_tier && (currentExpansionData.tier_exists || isGatheringProfession)">
                    <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-slate-600/5 blur-3xl -mr-16 -mt-16"></div>
                        <div class="relative z-10">
                            <div class="flex justify-between items-end mb-4">
                                <div>
                                    <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                                        <div class="w-2 h-6 sm:h-8 bg-slate-500 rounded-full"></div>
                                        {{ selectedProfession.profession_name }}
                                    </h3>
                                </div>
                                <div class="text-2xl sm:text-3xl font-black text-slate-600 font-mono">0%</div>
                            </div>
                            <p class="text-sm text-amber-500/80 italic">
                                Vous n'avez pas appris cette comp&eacute;tence pour cette extension.
                            </p>
                        </div>
                    </div>
                </template>

                <!-- Tier learned: full display -->
                <template v-else-if="currentExpansionData">
                    <!-- Progress Summary -->
                    <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-600/5 blur-3xl -mr-16 -mt-16"></div>
                        <div class="relative z-10">
                            <div class="flex justify-between items-end mb-4 sm:mb-6">
                                <div>
                                    <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                                        <div :class="['w-2 h-6 sm:h-8 rounded-full shadow-lg', isGatheringProfession ? 'bg-cyan-500 shadow-cyan-500/50' : 'bg-emerald-500 shadow-emerald-500/50']"></div>
                                        {{ selectedProfession.profession_name }}
                                    </h3>
                                    <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">{{ isGatheringProfession ? 'Niveau de comp\u00e9tence' : 'Recettes apprises par extension' }}</p>
                                </div>
                                <div class="text-right">
                                    <div :class="['text-2xl sm:text-3xl font-black font-mono', isGatheringProfession ? 'text-cyan-400' : 'text-emerald-400']">
                                        {{ Math.round(progressPercent) }}%
                                    </div>
                                    <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">
                                        <template v-if="isGatheringProfession">{{ currentExpansionData.skill_points }} / {{ currentExpansionData.max_skill_points }} points</template>
                                        <template v-else>{{ currentExpansionData.completed }} / {{ currentExpansionData.total }}</template>
                                    </div>
                                </div>
                            </div>
                            <!-- Progress bar -->
                            <div class="h-3 bg-slate-800 rounded-full overflow-hidden border border-white/5">
                                <div :class="['h-full transition-all duration-1000 relative', isGatheringProfession ? 'bg-linear-to-r from-cyan-700 via-cyan-500 to-cyan-400 shadow-[0_0_15px_rgba(6,182,212,0.3)]' : 'bg-linear-to-r from-emerald-700 via-emerald-500 to-emerald-400 shadow-[0_0_15px_rgba(16,185,129,0.3)]']" :style="{ width: progressPercent + '%' }">
                                    <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                                </div>
                            </div>
                            <!-- Skill points bar (only for crafting professions that also have skill points) -->
                            <div v-if="!isGatheringProfession && currentExpansionData.max_skill_points > 0" class="mt-3 flex items-center gap-3">
                                <span class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold shrink-0">Comp&eacute;tence</span>
                                <div class="flex-1 h-1.5 bg-slate-800 rounded-full overflow-hidden border border-white/5">
                                    <div
                                        class="h-full bg-linear-to-r from-cyan-700 via-cyan-500 to-cyan-400 transition-all duration-1000"
                                        :style="{ width: skillPointsPercent + '%' }"
                                    ></div>
                                </div>
                                <span class="text-[10px] sm:text-xs font-mono text-cyan-400 font-bold shrink-0">
                                    {{ currentExpansionData.skill_points }} / {{ currentExpansionData.max_skill_points }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <template v-if="sortedCategories.length">
                        <SearchFilter
                            v-model:search="search"
                            v-model:hideCompleted="hideCompleted"
                            placeholder="Rechercher une recette..."
                            hideLabel="Masquer apprises"
                        />

                        <!-- Category Cards Grid -->
                        <section v-if="filteredCategories.length">
                            <div class="flex justify-between items-center mb-4 sm:mb-6">
                                <h4 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                                    Cat&eacute;gories de recettes
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
                                    v-for="cat in paginatedCategories"
                                    :key="cat.name"
                                    @click="toggleCategory(cat)"
                                    class="bg-slate-800/40 border border-white/5 p-4 rounded-2xl hover:bg-slate-800/60 transition-colors group cursor-pointer"
                                >
                                    <div class="flex justify-between items-start mb-3">
                                        <span class="text-sm md:text-base font-bold text-slate-300 group-hover:text-emerald-400 transition-colors">{{ cat.name }}</span>
                                        <span class="text-[10px] sm:text-xs font-mono text-slate-500">{{ cat.completed }}/{{ cat.total }}</span>
                                    </div>
                                    <div class="h-1 bg-slate-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-600/80 transition-all duration-700" :style="{ width: (cat.completed / cat.total * 100) + '%' }"></div>
                                    </div>
                                    <div v-if="expandedCategory === cat.name" class="mt-4 pt-4 border-t border-white/5 space-y-1 max-h-48 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                                        <div v-for="item in sortedItems(cat.items)" :key="item.id" class="flex justify-between items-center text-[10px] sm:text-xs py-1">
                                            <a :href="wowheadLink(item)" target="_blank" rel="noopener" @click.stop :class="[item.is_completed ? 'text-emerald-400 font-medium' : 'text-slate-500', 'hover:underline']">{{ item.name }}</a>
                                            <span v-if="item.is_completed" class="text-green-500 font-bold">&check;</span>
                                            <span v-else class="text-slate-800">&cir;</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div v-else-if="search || hideCompleted" class="text-center py-8 text-slate-500 text-sm">
                            Aucun r&eacute;sultat trouv&eacute;.
                        </div>
                    </template>
                </template>
            </template>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useCharacterStore } from '../stores/character';
import ExpansionSelector from './ExpansionSelector.vue';
import SearchFilter from './SearchFilter.vue';

const store = useCharacterStore();

const selectedProfessionId = ref(null);
const activeExpansion = ref(store.latestExpansionId);
const expandedCategory = ref(null);
const page = ref(1);
const itemsPerPage = 8;
const search = ref('');
const hideCompleted = ref(false);

const professions = computed(() => store.character?.professions ?? []);

// Auto-select first profession
watch(professions, (profs) => {
    if (profs.length && !selectedProfessionId.value) {
        selectedProfessionId.value = profs[0].profession_id;
    }
}, { immediate: true });

const selectProfession = (id) => {
    selectedProfessionId.value = id;
    page.value = 1;
    expandedCategory.value = null;
    search.value = '';
    hideCompleted.value = false;
};

const selectedProfession = computed(() =>
    professions.value.find(p => p.profession_id === selectedProfessionId.value) || null
);

const isArchaeology = computed(() => selectedProfession.value?.is_archaeology === true);

// Archaeology global skill points (no per-expansion breakdown from Blizzard API)
const archaeologyPoints = computed(() => selectedProfession.value?.global_skill_points ?? 0);
const archaeologyMaxPoints = computed(() => selectedProfession.value?.global_max_skill_points ?? 0);

// Transform profession expansion data into ExpansionSelector format
const professionCollections = computed(() => {
    if (!selectedProfession.value) return {};
    const result = {};
    const useSkillPoints = isArchaeology.value || isGatheringProfession.value;
    const expansions = selectedProfession.value.expansions;
    for (const [expId, data] of Object.entries(expansions)) {
        const total = useSkillPoints ? (data.max_skill_points || 0) : data.total;
        const completed = useSkillPoints ? (data.skill_points || 0) : data.completed;
        result[expId] = { recipes: { total, completed } };
    }
    return result;
});

const currentExpansionData = computed(() => {
    if (!selectedProfession.value) return null;
    return selectedProfession.value.expansions?.[activeExpansion.value] || null;
});

// Gathering profession = no recipes across ALL expansions (Herbalism, Mining, Skinning)
const isGatheringProfession = computed(() => {
    if (!selectedProfession.value) return false;
    const expansions = selectedProfession.value.expansions;
    return Object.values(expansions).every(data => data.total === 0);
});

const progressPercent = computed(() => {
    if (!currentExpansionData.value) return 0;
    const { completed, total, skill_points, max_skill_points } = currentExpansionData.value;
    if (total > 0) return completed / total * 100;
    return max_skill_points > 0 ? skill_points / max_skill_points * 100 : 0;
});

const skillPointsPercent = computed(() => {
    if (!currentExpansionData.value) return 0;
    const { skill_points, max_skill_points } = currentExpansionData.value;
    return max_skill_points > 0 ? skill_points / max_skill_points * 100 : 0;
});

const sortedCategories = computed(() => {
    if (!currentExpansionData.value) return [];
    const categories = currentExpansionData.value.categories || [];
    return [...categories].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const filteredCategories = computed(() => {
    if (!search.value && !hideCompleted.value) return sortedCategories.value;

    const q = search.value.toLowerCase();
    return sortedCategories.value.map(cat => {
        let items = cat.items;
        if (search.value) {
            items = items.filter(i => i.name.toLowerCase().includes(q));
        }
        if (hideCompleted.value) {
            items = items.filter(i => !i.is_completed);
        }
        return { ...cat, items, total: items.length, completed: items.filter(i => i.is_completed).length };
    }).filter(cat => cat.items.length > 0);
});

const paginatedCategories = computed(() => {
    const start = (page.value - 1) * itemsPerPage;
    return filteredCategories.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil(filteredCategories.value.length / itemsPerPage));

const toggleCategory = (cat) => {
    expandedCategory.value = expandedCategory.value === cat.name ? null : cat.name;
};

const sortedItems = (items) => [...items].sort((a, b) => a.name.localeCompare(b.name, 'fr'));

const wowheadLink = (item) => item.wowhead_spell_id
    ? `https://www.wowhead.com/fr/spell=${item.wowhead_spell_id}`
    : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(item.name)}`;

watch(activeExpansion, () => {
    page.value = 1;
    expandedCategory.value = null;
    search.value = '';
    hideCompleted.value = false;
});

watch([search, hideCompleted], () => {
    page.value = 1;
    expandedCategory.value = null;
});
</script>
