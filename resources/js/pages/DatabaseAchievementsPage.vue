<template>
    <div class="space-y-6 py-6 sm:py-8">
        <BreadcrumbNav :crumbs="breadcrumbs" />

        <!-- Expansion selector tabs -->
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-3">
            <router-link
                v-for="exp in expansions"
                :key="exp.slug"
                :to="'/base-de-donnees/hauts-faits/' + exp.slug"
                :class="[
                    'px-3 sm:px-4 py-2 sm:py-3 rounded-xl sm:rounded-2xl text-[11px] sm:text-[13px] font-bold transition-all border flex flex-col items-center gap-1 group relative overflow-hidden',
                    activeSlug === exp.slug
                        ? 'bg-amber-600 border-amber-400 text-white shadow-xl shadow-amber-500/20 scale-105 z-10'
                        : 'bg-slate-800/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-700 hover:border-white/10'
                ]"
            >
                <span class="relative z-10 text-center">{{ exp.name }}</span>
                <div :class="[
                    'text-[9px] font-mono px-2 py-0.5 rounded-full border relative z-10',
                    activeSlug === exp.slug ? 'bg-amber-700/50 border-white/20' : 'bg-slate-800 border-white/5 opacity-60'
                ]">
                    {{ exp.count }}
                </div>
            </router-link>
        </div>

        <!-- Header card -->
        <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-amber-600/5 blur-3xl -mr-16 -mt-16"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-end mb-4 sm:mb-6">
                    <div>
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                            <div class="w-2 h-6 sm:h-8 bg-amber-500 rounded-full shadow-lg shadow-amber-500/50"></div>
                            Hauts-faits
                        </h1>
                        <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">{{ activeExpansionName || 'Toutes les extensions' }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl sm:text-3xl font-black text-amber-400 font-mono">
                            {{ displayCount.toLocaleString('fr-FR') }}
                        </div>
                        <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">hauts-faits</div>
                    </div>
                </div>
            </div>
        </div>

        <SearchFilter
            v-model:search="search"
            placeholder="Rechercher un haut-fait..."
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
                        <span class="text-sm md:text-base font-bold text-slate-300 group-hover:text-amber-400 transition-colors">{{ cat.name || 'Général' }}</span>
                        <span class="text-[10px] sm:text-xs font-mono text-slate-500">{{ cat.count }} &middot; {{ cat.points }} pts</span>
                    </div>
                    <div v-if="expandedCategory === cat.name" class="mt-3 pt-3 border-t border-white/5 space-y-1 max-h-96 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                        <div v-for="item in cat.items" :key="item.id" class="flex items-center gap-3 text-xs sm:text-sm py-1.5">
                            <CollectionIcon :src="item.icon_url" :alt="item.name_fr" fallback="HF" size="sm" class="text-amber-500" />
                            <a :href="`https://www.wowhead.com/fr/achievement=${item.id}`" target="_blank" rel="noopener" @click.stop class="text-slate-400 hover:text-amber-400 hover:underline flex-1 truncate">{{ item.name_fr }}</a>
                            <span class="text-[10px] font-mono text-slate-600 shrink-0">{{ item.points }} pts</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="loading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>
        <div v-else-if="filteredCategories.length === 0 && !loading" class="text-center py-8 text-slate-500 text-sm">
            Aucun résultat trouvé.
        </div>

        <div class="text-center text-xs text-slate-600 pt-4">
            <router-link to="/base-de-donnees" class="hover:text-slate-400 transition-colors">&larr; Retour à la base de données</router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import BreadcrumbNav from '../components/BreadcrumbNav.vue';
import SearchFilter from '../components/SearchFilter.vue';
import CollectionIcon from '../components/CollectionIcon.vue';

const route = useRoute();
const loading = ref(true);
const items = ref([]);
const expansions = ref([]);
const total = ref(0);
const search = ref('');
const page = ref(1);
const itemsPerPage = 8;
const expandedCategory = ref(null);

const activeSlug = computed(() => route.params.expansion || '');
const activeExpansionName = computed(() => {
    const exp = expansions.value.find(e => e.slug === activeSlug.value);
    return exp?.name || '';
});

const displayCount = computed(() => activeSlug.value ? items.value.length : total.value);

const breadcrumbs = computed(() => {
    const crumbs = [
        { label: 'Base de données', to: '/base-de-donnees' },
    ];
    if (activeSlug.value) {
        crumbs.push({ label: 'Hauts-faits', to: '/base-de-donnees/hauts-faits' });
        crumbs.push({ label: activeExpansionName.value || activeSlug.value });
    } else {
        crumbs.push({ label: 'Hauts-faits' });
    }
    return crumbs;
});

const categoryMap = computed(() => {
    const map = {};
    for (const item of items.value) {
        const cat = item.category_name || '';
        if (!map[cat]) map[cat] = [];
        map[cat].push(item);
    }
    return map;
});

const filteredCategories = computed(() => {
    const q = search.value.toLowerCase();
    return Object.entries(categoryMap.value)
        .map(([name, catItems]) => {
            const filtered = q ? catItems.filter(i => i.name_fr.toLowerCase().includes(q)) : catItems;
            return {
                name,
                items: filtered,
                count: filtered.length,
                points: filtered.reduce((sum, i) => sum + (i.points || 0), 0),
            };
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

async function fetchData() {
    loading.value = true;
    try {
        const params = {};
        if (activeSlug.value) params.expansion = activeSlug.value;
        const { data } = await axios.get('/api/database/achievements', { params });
        items.value = data.items;
        expansions.value = data.expansions;
        total.value = data.total;
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

watch(() => route.params.expansion, () => {
    page.value = 1;
    search.value = '';
    expandedCategory.value = null;
    fetchData();
});

watch(search, () => {
    page.value = 1;
    expandedCategory.value = null;
});

onMounted(fetchData);
</script>
