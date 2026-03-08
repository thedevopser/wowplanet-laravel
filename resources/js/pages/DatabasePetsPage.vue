<template>
    <div class="space-y-6 py-6 sm:py-8">
        <BreadcrumbNav :crumbs="breadcrumbs" />

        <!-- Category selector tabs -->
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-3">
            <router-link
                v-for="cat in categories"
                :key="cat.slug"
                :to="'/base-de-donnees/mascottes/' + cat.slug"
                :class="[
                    'px-3 sm:px-4 py-2 sm:py-3 rounded-xl sm:rounded-2xl text-[11px] sm:text-[13px] font-bold transition-all border flex flex-col items-center gap-1 group relative overflow-hidden',
                    activeSlug === cat.slug
                        ? 'bg-blue-600 border-blue-400 text-white shadow-xl shadow-blue-500/20 scale-105 z-10'
                        : 'bg-slate-800/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-700 hover:border-white/10'
                ]"
            >
                <span class="relative z-10">{{ cat.name }}</span>
                <div :class="[
                    'text-[9px] font-mono px-2 py-0.5 rounded-full border relative z-10',
                    activeSlug === cat.slug ? 'bg-blue-700/50 border-white/20' : 'bg-slate-800 border-white/5 opacity-60'
                ]">
                    {{ cat.count }}
                </div>
            </router-link>
        </div>

        <!-- Header card -->
        <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/5 blur-3xl -mr-16 -mt-16"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-end mb-4 sm:mb-6">
                    <div>
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                            <div class="w-2 h-6 sm:h-8 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50"></div>
                            Mascottes
                        </h1>
                        <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">{{ activeCategoryName || 'Toutes les catégories' }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl sm:text-3xl font-black text-blue-400 font-mono">
                            {{ total.toLocaleString('fr-FR') }}
                        </div>
                        <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">mascottes</div>
                    </div>
                </div>
            </div>
        </div>

        <SearchFilter
            v-model:search="search"
            placeholder="Rechercher une mascotte..."
            :show-hide-toggle="false"
        >
            <template #extra-toggles></template>
        </SearchFilter>

        <!-- Source groups -->
        <section v-if="filteredSources.length">
            <div class="flex justify-between items-center mb-4 sm:mb-6">
                <h2 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                    Sources
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
                    v-for="src in paginatedSources"
                    :key="src.name"
                    @click="toggleSource(src)"
                    class="bg-slate-800/40 border border-white/5 p-4 rounded-2xl hover:bg-slate-800/60 transition-colors group cursor-pointer"
                >
                    <div class="flex justify-between items-start mb-3">
                        <span class="text-sm md:text-base font-bold text-slate-300 group-hover:text-blue-400 transition-colors">{{ src.name }}</span>
                        <span class="text-[10px] sm:text-xs font-mono text-slate-500">{{ src.count }}</span>
                    </div>
                    <div v-if="expandedSource === src.name" class="mt-3 pt-3 border-t border-white/5 space-y-1 max-h-96 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                        <div v-for="item in src.items" :key="item.id" class="flex items-center gap-3 text-xs sm:text-sm py-1.5">
                            <CollectionIcon :src="item.icon_url" :alt="item.name_fr" fallback="P" size="sm" class="text-blue-500" />
                            <a :href="item.creature_id ? `https://www.wowhead.com/fr/npc=${item.creature_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(item.name_fr)}`" target="_blank" rel="noopener" @click.stop class="text-slate-400 hover:text-blue-400 hover:underline flex-1 truncate">{{ item.name_fr }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Items without source (flat list) -->
        <section v-if="uncategorizedItems.length">
            <h2 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1 mb-4">
                Mascottes
                <div class="flex-1 h-px bg-slate-700"></div>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <a v-for="item in uncategorizedItems" :key="item.id" :href="item.creature_id ? `https://www.wowhead.com/fr/npc=${item.creature_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(item.name_fr)}`" target="_blank" rel="noopener" class="flex items-center gap-3 p-3 sm:p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-blue-500/30 transition-all">
                    <CollectionIcon :src="item.icon_url" :alt="item.name_fr" fallback="P" size="lg" class="text-blue-500 group-hover:scale-110 transition-transform" />
                    <div class="flex-1 min-w-0">
                        <div class="text-sm md:text-base font-bold text-slate-200 group-hover:text-blue-400 transition-colors truncate">{{ item.name_fr }}</div>
                        <div class="text-[10px] sm:text-xs text-slate-500 font-mono">{{ item.source || 'Source inconnue' }}</div>
                    </div>
                </a>
            </div>
        </section>

        <div v-if="loading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>
        <div v-else-if="filteredSources.length === 0 && uncategorizedItems.length === 0 && !loading" class="text-center py-8 text-slate-500 text-sm">
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
const categories = ref([]);
const total = ref(0);
const search = ref('');
const page = ref(1);
const itemsPerPage = 8;
const expandedSource = ref(null);

const activeSlug = computed(() => route.params.category || '');
const activeCategoryName = computed(() => {
    const cat = categories.value.find(c => c.slug === activeSlug.value);
    return cat?.name || '';
});

const breadcrumbs = computed(() => {
    const crumbs = [
        { label: 'Base de données', to: '/base-de-donnees' },
    ];
    if (activeSlug.value) {
        crumbs.push({ label: 'Mascottes', to: '/base-de-donnees/mascottes' });
        crumbs.push({ label: activeCategoryName.value || activeSlug.value });
    } else {
        crumbs.push({ label: 'Mascottes' });
    }
    return crumbs;
});

const sourceMap = computed(() => {
    const map = {};
    for (const item of items.value) {
        const src = item.source || 'Autre';
        if (!map[src]) map[src] = [];
        map[src].push(item);
    }
    return map;
});

const filteredSources = computed(() => {
    const q = search.value.toLowerCase();
    return Object.entries(sourceMap.value)
        .map(([name, srcItems]) => {
            const filtered = q ? srcItems.filter(i => i.name_fr.toLowerCase().includes(q)) : srcItems;
            return { name, items: filtered, count: filtered.length };
        })
        .filter(s => s.count > 0)
        .sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const paginatedSources = computed(() => {
    const start = (page.value - 1) * itemsPerPage;
    return filteredSources.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredSources.value.length / itemsPerPage)));

const uncategorizedItems = computed(() => {
    if (filteredSources.value.length > 0) return [];
    const q = search.value.toLowerCase();
    return q ? items.value.filter(i => i.name_fr.toLowerCase().includes(q)) : items.value;
});

const toggleSource = (src) => {
    expandedSource.value = expandedSource.value === src.name ? null : src.name;
};

async function fetchData() {
    loading.value = true;
    try {
        const params = {};
        if (activeSlug.value) params.category = activeSlug.value;
        const { data } = await axios.get('/api/database/pets', { params });
        items.value = data.items;
        categories.value = data.categories;
        total.value = activeSlug.value ? data.items.length : data.total;
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

watch(() => route.params.category, () => {
    page.value = 1;
    search.value = '';
    expandedSource.value = null;
    fetchData();
});

watch(search, () => {
    page.value = 1;
    expandedSource.value = null;
});

onMounted(fetchData);
</script>
