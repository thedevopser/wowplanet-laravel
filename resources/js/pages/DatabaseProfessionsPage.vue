<template>
    <div class="space-y-6 py-6 sm:py-8">
        <Head>
            <title>{{ meta.title }}</title>
            <meta name="description" :content="meta.description">
            <link rel="canonical" :href="meta.canonicalUrl">
            <meta property="og:type" :content="meta.ogType">
            <meta property="og:title" :content="meta.ogTitle">
            <meta property="og:description" :content="meta.ogDescription">
            <meta property="og:image" :content="meta.ogImage">
            <meta property="og:url" :content="meta.ogUrl">
            <meta property="og:site_name" content="WowPlanet">
            <meta property="og:locale" content="fr_FR">
        </Head>

        <!-- Profession list (when no profession selected) -->
        <template v-if="!profession">
            <DatabasePageHeader
                title="Professions"
                subtitle="Toutes les professions de World of Warcraft"
                :count="total_recipes"
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
                    <Link
                        v-for="prof in primaryProfessions"
                        :key="prof.id"
                        :href="'/base-de-donnees/professions/' + prof.slug"
                        class="flex items-center gap-4 p-4 sm:p-5 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-emerald-500/30 hover:bg-slate-800/60 transition-all"
                    >
                        <div class="w-12 h-12 bg-emerald-600/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 text-lg font-bold shrink-0 group-hover:scale-110 transition-transform">
                            {{ prof.name_fr.charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm md:text-base font-bold text-slate-200 group-hover:text-emerald-400 transition-colors">{{ prof.name_fr }}</div>
                            <div class="text-xs text-slate-500 font-mono">{{ prof.recipe_count.toLocaleString('fr-FR') }} recettes</div>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- Secondary professions -->
            <section v-if="secondaryProfessions.length">
                <h2 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 mb-4">
                    Professions secondaires
                    <div class="flex-1 h-px bg-slate-700"></div>
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link
                        v-for="prof in secondaryProfessions"
                        :key="prof.id"
                        :href="'/base-de-donnees/professions/' + prof.slug"
                        class="flex items-center gap-4 p-4 sm:p-5 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-emerald-500/30 hover:bg-slate-800/60 transition-all"
                    >
                        <div class="w-12 h-12 bg-emerald-600/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 text-lg font-bold shrink-0 group-hover:scale-110 transition-transform">
                            {{ prof.name_fr.charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm md:text-base font-bold text-slate-200 group-hover:text-emerald-400 transition-colors">{{ prof.name_fr }}</div>
                            <div class="text-xs text-slate-500 font-mono">{{ prof.recipe_count.toLocaleString('fr-FR') }} recettes</div>
                        </div>
                    </Link>
                </div>
            </section>
        </template>

        <!-- Profession detail (recipes) -->
        <template v-else>
            <!-- Expansion tabs -->
            <div v-if="recipeExpansions.length" class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-3">
                <button
                    v-for="exp in recipeExpansions"
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
                :count="recipeTotal"
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
            <div v-if="recipeList.length" class="overflow-x-auto">
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
                            v-for="recipe in recipeList"
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
                :total="recipeTotal"
                @page-change="onPageChange"
            />

            <div v-if="recipeList.length === 0" class="text-center py-8 text-slate-500 text-sm">
                Aucun résultat trouvé.
            </div>
        </template>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';
import DatabaseLayout from '../layouts/DatabaseLayout.vue';

export default {
    layout: [AppLayout, DatabaseLayout],
};
</script>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import SearchFilter from '../components/SearchFilter.vue';
import DatabasePageHeader from '../components/DatabasePageHeader.vue';
import DatabasePagination from '../components/DatabasePagination.vue';

const props = defineProps({
    meta: { type: Object, required: true },
    profession: { type: String, default: null },
    expansion: { type: String, default: null },
    search: { type: String, default: null },
    professions: { type: Array, default: () => [] },
    total_recipes: { type: Number, default: 0 },
    // Détail d'une profession : { items, expansions, profession, total, current_page, last_page } ou null.
    recipes: { type: Object, default: null },
});

const page = usePage();
const search = ref(props.search ?? '');

const primaryProfessions = computed(() => props.professions.filter(p => p.type === 'primary'));
const secondaryProfessions = computed(() => props.professions.filter(p => p.type === 'secondary'));

const recipeList = computed(() => props.recipes?.items ?? []);
const recipeExpansions = computed(() => props.recipes?.expansions ?? []);
const professionName = computed(() => props.recipes?.profession?.name_fr ?? '');
const recipeTotal = computed(() => props.recipes?.total ?? 0);
const currentPage = computed(() => props.recipes?.current_page ?? 1);
const lastPage = computed(() => props.recipes?.last_page ?? 1);
const activeExpansion = computed(() => props.expansion ?? '');
const activeExpansionName = computed(() => {
    const exp = recipeExpansions.value.find(e => e.slug === activeExpansion.value);
    return exp?.name || '';
});

const dataOnly = ['recipes', 'expansion', 'search'];

function basePath() {
    return page.url.split('?')[0];
}

function visitRecipes(params, options = {}) {
    router.get(basePath(), {
        expansion: params.expansion || undefined,
        page: params.page,
        search: params.search || undefined,
    }, {
        preserveState: true,
        only: dataOnly,
        ...options,
    });
}

function toggleExpansion(slug) {
    const nextExpansion = activeExpansion.value === slug ? '' : slug;
    visitRecipes({ expansion: nextExpansion, page: 1, search: search.value });
}

function onPageChange(newPage) {
    visitRecipes({ expansion: activeExpansion.value, page: newPage, search: search.value });
}

function onSearchDebounced(value) {
    visitRecipes({ expansion: activeExpansion.value, page: 1, search: value }, { preserveScroll: true });
}
</script>
