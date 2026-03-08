<template>
    <div class="space-y-6 py-6 sm:py-8">
        <BreadcrumbNav :crumbs="breadcrumbs" />

        <!-- Profession list (when no profession selected) -->
        <template v-if="!activeProfessionSlug">
            <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-600/5 blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10">
                    <div class="flex justify-between items-end mb-4 sm:mb-6">
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                                <div class="w-2 h-6 sm:h-8 bg-emerald-500 rounded-full shadow-lg shadow-emerald-500/50"></div>
                                Professions
                            </h1>
                            <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">Toutes les professions de World of Warcraft</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono">
                                {{ totalRecipes.toLocaleString('fr-FR') }}
                            </div>
                            <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">recettes</div>
                        </div>
                    </div>
                </div>
            </div>

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
                    @click="activeExpansion = activeExpansion === exp.slug ? '' : exp.slug"
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

            <!-- Header card -->
            <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-600/5 blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10">
                    <div class="flex justify-between items-end mb-4 sm:mb-6">
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                                <div class="w-2 h-6 sm:h-8 bg-emerald-500 rounded-full shadow-lg shadow-emerald-500/50"></div>
                                {{ professionName }}
                            </h1>
                            <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">{{ activeExpansionName || 'Toutes les extensions' }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono">
                                {{ recipes.length.toLocaleString('fr-FR') }}
                            </div>
                            <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">recettes</div>
                        </div>
                    </div>
                </div>
            </div>

            <SearchFilter
                v-model:search="search"
                placeholder="Rechercher une recette..."
                :show-hide-toggle="false"
            >
                <template #extra-toggles></template>
            </SearchFilter>

            <!-- Category groups -->
            <section v-if="filteredCategories.length">
                <div class="flex justify-between items-center mb-4 sm:mb-6">
                    <h2 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                        Catégories
                        <div class="flex-1 h-px bg-slate-700"></div>
                    </h2>
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
                            <span class="text-[10px] sm:text-xs font-mono text-slate-500">{{ cat.count }}</span>
                        </div>
                        <div v-if="expandedCategory === cat.name" class="mt-3 pt-3 border-t border-white/5 space-y-1 max-h-96 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                            <div v-for="recipe in cat.items" :key="recipe.id" class="flex items-center gap-3 text-xs sm:text-sm py-1.5">
                                <a :href="recipe.wowhead_spell_id ? `https://www.wowhead.com/fr/spell=${recipe.wowhead_spell_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(recipe.name_fr)}`" target="_blank" rel="noopener" @click.stop class="text-slate-400 hover:text-emerald-400 hover:underline flex-1 truncate">{{ recipe.name_fr }}</a>
                                <span v-if="recipe.faction" class="text-[9px] font-mono px-1.5 py-0.5 rounded border" :class="recipe.faction === 'Alliance' ? 'text-blue-400 border-blue-500/30 bg-blue-500/10' : 'text-red-400 border-red-500/30 bg-red-500/10'">{{ recipe.faction }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>
            <div v-else-if="filteredCategories.length === 0 && !loading" class="text-center py-8 text-slate-500 text-sm">
                Aucun résultat trouvé.
            </div>
        </template>

        <div v-if="listLoading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>

        <div class="text-center text-xs text-slate-600 pt-4">
            <router-link v-if="activeProfessionSlug" to="/base-de-donnees/professions" class="hover:text-slate-400 transition-colors">&larr; Toutes les professions</router-link>
            <router-link v-else to="/base-de-donnees" class="hover:text-slate-400 transition-colors">&larr; Retour à la base de données</router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import BreadcrumbNav from '../components/BreadcrumbNav.vue';
import SearchFilter from '../components/SearchFilter.vue';

const route = useRoute();
const listLoading = ref(true);
const loading = ref(false);
const professions = ref([]);
const totalRecipes = ref(0);
const recipes = ref([]);
const expansions = ref([]);
const professionName = ref('');
const search = ref('');
const page = ref(1);
const itemsPerPage = 8;
const expandedCategory = ref(null);
const activeExpansion = ref('');

const activeProfessionSlug = computed(() => route.params.profession || '');

const primaryProfessions = computed(() => professions.value.filter(p => p.type === 'primary'));
const secondaryProfessions = computed(() => professions.value.filter(p => p.type === 'secondary'));

const activeExpansionName = computed(() => {
    const exp = expansions.value.find(e => e.slug === activeExpansion.value);
    return exp?.name || '';
});

const breadcrumbs = computed(() => {
    const crumbs = [
        { label: 'Base de données', to: '/base-de-donnees' },
    ];
    if (activeProfessionSlug.value) {
        crumbs.push({ label: 'Professions', to: '/base-de-donnees/professions' });
        crumbs.push({ label: professionName.value || activeProfessionSlug.value });
    } else {
        crumbs.push({ label: 'Professions' });
    }
    return crumbs;
});

const categoryMap = computed(() => {
    const map = {};
    for (const recipe of recipes.value) {
        const cat = recipe.category_name || 'Autre';
        if (!map[cat]) map[cat] = [];
        map[cat].push(recipe);
    }
    return map;
});

const filteredCategories = computed(() => {
    const q = search.value.toLowerCase();
    return Object.entries(categoryMap.value)
        .map(([name, catRecipes]) => {
            const filtered = q ? catRecipes.filter(r => r.name_fr.toLowerCase().includes(q)) : catRecipes;
            return { name, items: filtered, count: filtered.length };
        })
        .filter(c => c.count > 0)
        .sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const paginatedCategories = computed(() => {
    const start = (page.value - 1) * itemsPerPage;
    return filteredCategories.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredCategories.value.length / itemsPerPage)));

const toggleCategory = (cat) => {
    expandedCategory.value = expandedCategory.value === cat.name ? null : cat.name;
};

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
        const params = { profession: activeProfessionSlug.value };
        if (activeExpansion.value) params.expansion = activeExpansion.value;
        const { data } = await axios.get('/api/database/professions/recipes', { params });
        recipes.value = data.items;
        expansions.value = data.expansions;
        professionName.value = data.profession?.name_fr || '';
    } catch {
        recipes.value = [];
    } finally {
        loading.value = false;
    }
}

watch(() => route.params.profession, () => {
    page.value = 1;
    search.value = '';
    expandedCategory.value = null;
    activeExpansion.value = '';
    if (activeProfessionSlug.value) {
        fetchRecipes();
    } else {
        fetchProfessionList();
    }
});

watch(activeExpansion, () => {
    page.value = 1;
    search.value = '';
    expandedCategory.value = null;
    if (activeProfessionSlug.value) {
        fetchRecipes();
    }
});

watch(search, () => {
    page.value = 1;
    expandedCategory.value = null;
});

onMounted(() => {
    if (activeProfessionSlug.value) {
        fetchRecipes();
    } else {
        fetchProfessionList();
    }
});
</script>
