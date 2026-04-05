<template>
    <div class="space-y-6 py-6 sm:py-8">
        <!-- Profession list (when no profession selected) -->
        <template v-if="!activeProfessionSlug">
            <DatabasePageHeader
                title="Professions"
                subtitle="Toutes les professions de World of Warcraft"
                :count="totalRecipes"
                count-label="recettes"
                accent-color="emerald"
            />

            <!-- Primary professions -->
            <section v-if="primaryProfessions.length">
                <h2 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 mb-4">
                    Professions principales
                    <div class="flex-1 h-px bg-slate-700"></div>
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <router-link
                        v-for="prof in primaryProfessions"
                        :key="prof.id"
                        :to="'/base-de-donnees/professions/' + prof.slug"
                        class="flex items-center gap-4 p-4 sm:p-5 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-emerald-500/30 hover:bg-slate-800/60 transition-all"
                    >
                        <div class="w-12 h-12 bg-emerald-600/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 text-lg font-bold shrink-0 group-hover:scale-110 transition-transform">
                            {{ prof.name_fr.charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm md:text-base font-bold text-slate-200 group-hover:text-emerald-400 transition-colors">{{ prof.name_fr }}</div>
                            <div class="text-xs text-slate-500 font-mono">{{ prof.recipe_count.toLocaleString('fr-FR') }} recettes</div>
                        </div>
                    </router-link>
                </div>
            </section>

            <!-- Secondary professions -->
            <section v-if="secondaryProfessions.length">
                <h2 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 mb-4">
                    Professions secondaires
                    <div class="flex-1 h-px bg-slate-700"></div>
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <router-link
                        v-for="prof in secondaryProfessions"
                        :key="prof.id"
                        :to="'/base-de-donnees/professions/' + prof.slug"
                        class="flex items-center gap-4 p-4 sm:p-5 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-emerald-500/30 hover:bg-slate-800/60 transition-all"
                    >
                        <div class="w-12 h-12 bg-emerald-600/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 text-lg font-bold shrink-0 group-hover:scale-110 transition-transform">
                            {{ prof.name_fr.charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm md:text-base font-bold text-slate-200 group-hover:text-emerald-400 transition-colors">{{ prof.name_fr }}</div>
                            <div class="text-xs text-slate-500 font-mono">{{ prof.recipe_count.toLocaleString('fr-FR') }} recettes</div>
                        </div>
                    </router-link>
                </div>
            </section>
        </template>

        <!-- Profession detail (recipes) -->
        <template v-else>
            <!-- Expansion tabs -->
            <div v-if="expansions.length" class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-3">
                <button
                    v-for="exp in expansions"
                    :key="exp.slug"
                    @click="toggleExpansion(exp.slug)"
                    :class="[
                        'px-3 sm:px-4 py-2 sm:py-3 rounded-xl sm:rounded-2xl text-[11px] sm:text-[13px] font-bold transition-all border flex flex-col items-center gap-1',
                        activeExpansion === exp.slug
                            ? 'bg-emerald-600 border-emerald-400 text-white shadow-xl shadow-emerald-500/20 scale-105 z-10'
                            : 'bg-slate-800/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-700 hover:border-white/10'
                    ]"
                >
                    <span>{{ exp.name }}</span>
                    <div :class="[
                        'text-[9px] font-mono px-2 py-0.5 rounded-full border',
                        activeExpansion === exp.slug ? 'bg-emerald-700/50 border-white/20' : 'bg-slate-800 border-white/5 opacity-60'
                    ]">
                        {{ exp.count }}
                    </div>
                </button>
            </div>

            <DatabasePageHeader
                :title="professionName"
                :subtitle="activeExpansionName || 'Toutes les extensions'"
                :count="total"
                count-label="recettes"
                accent-color="emerald"
            />

            <SearchFilter
                v-model:search="search"
                placeholder="Rechercher une recette..."
                :show-hide-toggle="false"
                :debounce-ms="300"
                @search-debounced="onSearchDebounced"
            />

            <!-- Flat table of all recipes -->
            <div v-if="recipes.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10">
                            <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3">Nom</th>
                            <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 hidden sm:table-cell">Catégorie</th>
                            <th class="text-right text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 w-24 hidden sm:table-cell">Faction</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="recipe in recipes"
                            :key="recipe.id"
                            class="border-b border-white/3 even:bg-slate-800/20 hover:bg-slate-800/40 transition-colors"
                        >
                            <td class="py-2">
                                <a :href="recipe.wowhead_spell_id ? `https://www.wowhead.com/fr/spell=${recipe.wowhead_spell_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(recipe.name_fr)}`" target="_blank" rel="noopener" class="text-slate-300 hover:text-emerald-400 hover:underline">{{ recipe.name_fr }}</a>
                            </td>
                            <td class="py-2 text-slate-500 text-xs hidden sm:table-cell">{{ recipe.category_name }}</td>
                            <td class="py-2 text-right hidden sm:table-cell">
                                <span
                                    v-if="recipe.faction"
                                    class="text-[9px] font-mono px-1.5 py-0.5 rounded border"
                                    :class="recipe.faction === 'Alliance'
                                        ? 'text-blue-400 border-blue-500/20 bg-blue-500/10'
                                        : 'text-red-400 border-red-500/20 bg-red-500/10'"
                                >{{ recipe.faction }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <DatabasePagination
                :current-page="currentPage"
                :last-page="lastPage"
                :total="total"
                @page-change="onPageChange"
            />

            <div v-if="loading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>
            <div v-else-if="recipes.length === 0 && !loading" class="text-center py-8 text-slate-500 text-sm">
                Aucun résultat trouvé.
            </div>
        </template>

        <div v-if="listLoading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import SearchFilter from '../components/SearchFilter.vue';
import DatabasePageHeader from '../components/DatabasePageHeader.vue';
import DatabasePagination from '../components/DatabasePagination.vue';

const route = useRoute();
const listLoading = ref(true);
const loading = ref(false);
const professions = ref([]);
const totalRecipes = ref(0);
const recipes = ref([]);
const expansions = ref([]);
const professionName = ref('');
const search = ref('');
const serverSearch = ref('');
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const activeExpansion = ref('');

const activeProfessionSlug = computed(() => route.params.profession || '');

const primaryProfessions = computed(() => professions.value.filter(p => p.type === 'primary'));
const secondaryProfessions = computed(() => professions.value.filter(p => p.type === 'secondary'));

const activeExpansionName = computed(() => {
    const exp = expansions.value.find(e => e.slug === activeExpansion.value);
    return exp?.name || '';
});

function toggleExpansion(slug) {
    activeExpansion.value = activeExpansion.value === slug ? '' : slug;
}

async function fetchProfessionList() {
    listLoading.value = true;
    try {
        const { data } = await axios.get('/api/database/professions');
        professions.value = data.professions;
        totalRecipes.value = data.total_recipes;
    } catch {
        professions.value = [];
    } finally {
        listLoading.value = false;
    }
}

async function fetchRecipes() {
    loading.value = true;
    try {
        const params = { profession: activeProfessionSlug.value, page: currentPage.value };
        if (activeExpansion.value) params.expansion = activeExpansion.value;
        if (serverSearch.value) params.search = serverSearch.value;
        const { data } = await axios.get('/api/database/professions/recipes', { params });
        recipes.value = data.items;
        expansions.value = data.expansions;
        professionName.value = data.profession?.name_fr || '';
        total.value = data.total || 0;
        lastPage.value = data.last_page || 1;
        currentPage.value = data.current_page || 1;
    } catch {
        recipes.value = [];
    } finally {
        loading.value = false;
    }
}

function onPageChange(page) {
    currentPage.value = page;
    fetchRecipes();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function onSearchDebounced(value) {
    serverSearch.value = value;
    currentPage.value = 1;
    fetchRecipes();
}

watch(() => route.params.profession, () => {
    currentPage.value = 1;
    search.value = '';
    serverSearch.value = '';
    activeExpansion.value = '';
    if (activeProfessionSlug.value) {
        fetchRecipes();
    } else {
        fetchProfessionList();
    }
});

watch(activeExpansion, () => {
    currentPage.value = 1;
    search.value = '';
    serverSearch.value = '';
    if (activeProfessionSlug.value) {
        fetchRecipes();
    }
});

onMounted(() => {
    if (activeProfessionSlug.value) {
        fetchRecipes();
    } else {
        fetchProfessionList();
    }
});
</script>
