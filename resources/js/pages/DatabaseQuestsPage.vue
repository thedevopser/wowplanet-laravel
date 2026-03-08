<template>
    <div class="space-y-6 py-6 sm:py-8">
        <BreadcrumbNav :crumbs="breadcrumbs" />

        <!-- Expansion selector tabs -->
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-3">
            <router-link
                v-for="exp in expansions"
                :key="exp.slug"
                :to="'/base-de-donnees/quetes/' + exp.slug"
                :class="[
                    'px-3 sm:px-4 py-2 sm:py-3 rounded-xl sm:rounded-2xl text-[11px] sm:text-[13px] font-bold transition-all border flex flex-col items-center gap-1 group relative overflow-hidden',
                    activeExpansionSlug === exp.slug
                        ? 'bg-blue-600 border-blue-400 text-white shadow-xl shadow-blue-500/20 scale-105 z-10'
                        : 'bg-slate-800/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-700 hover:border-white/10'
                ]"
            >
                <span class="relative z-10 text-center">{{ exp.name }}</span>
                <div :class="[
                    'text-[9px] font-mono px-2 py-0.5 rounded-full border relative z-10',
                    activeExpansionSlug === exp.slug ? 'bg-blue-700/50 border-white/20' : 'bg-slate-800 border-white/5 opacity-60'
                ]">
                    {{ exp.count }}
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
                            Quêtes
                        </h1>
                        <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">
                            {{ activeExpansionName || 'Toutes les extensions' }}
                            <span v-if="activeZoneName"> &middot; {{ activeZoneName }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl sm:text-3xl font-black text-blue-400 font-mono">
                            {{ displayCount.toLocaleString('fr-FR') }}
                        </div>
                        <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">quêtes</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zone selector (when expansion is selected) -->
        <div v-if="zones.length > 0" class="flex flex-wrap gap-2">
            <router-link
                :to="'/base-de-donnees/quetes/' + activeExpansionSlug"
                :class="[
                    'px-3 py-1.5 rounded-lg text-xs font-medium border transition-all',
                    !activeZoneSlug
                        ? 'bg-blue-500/15 border-blue-500/30 text-blue-400'
                        : 'bg-slate-800/60 border-white/10 text-slate-500 hover:text-slate-300 hover:border-white/20'
                ]"
            >Toutes ({{ items.length + filteredItems.length }})</router-link>
            <router-link
                v-for="z in zones"
                :key="z.slug"
                :to="'/base-de-donnees/quetes/' + activeExpansionSlug + '/' + z.slug"
                :class="[
                    'px-3 py-1.5 rounded-lg text-xs font-medium border transition-all',
                    activeZoneSlug === z.slug
                        ? 'bg-blue-500/15 border-blue-500/30 text-blue-400'
                        : 'bg-slate-800/60 border-white/10 text-slate-500 hover:text-slate-300 hover:border-white/20'
                ]"
            >{{ z.name }} ({{ z.count }})</router-link>
        </div>

        <SearchFilter
            v-model:search="search"
            placeholder="Rechercher une quête..."
            :show-hide-toggle="false"
        >
            <template #extra-toggles></template>
        </SearchFilter>

        <!-- Quest list grouped by zone -->
        <section v-if="filteredZoneGroups.length">
            <div class="flex justify-between items-center mb-4 sm:mb-6">
                <h2 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                    Zones
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
                    v-for="zone in paginatedZoneGroups"
                    :key="zone.name"
                    @click="toggleZone(zone)"
                    class="bg-slate-800/40 border border-white/5 p-4 rounded-2xl hover:bg-slate-800/60 transition-colors group cursor-pointer"
                >
                    <div class="flex justify-between items-start mb-3">
                        <span class="text-sm md:text-base font-bold text-slate-300 group-hover:text-blue-400 transition-colors">{{ zone.name || 'Zone inconnue' }}</span>
                        <span class="text-[10px] sm:text-xs font-mono text-slate-500">{{ zone.count }}</span>
                    </div>
                    <div v-if="expandedZone === zone.name" class="mt-3 pt-3 border-t border-white/5 space-y-1 max-h-96 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                        <div v-for="item in zone.items" :key="item.id" class="flex items-center gap-3 text-xs sm:text-sm py-1.5">
                            <a :href="`https://www.wowhead.com/fr/quest=${item.id}`" target="_blank" rel="noopener" @click.stop class="text-slate-400 hover:text-blue-400 hover:underline flex-1 truncate">{{ item.name_fr }}</a>
                            <span v-if="item.faction" class="text-[9px] font-mono px-1.5 py-0.5 rounded border shrink-0" :class="item.faction === 'Alliance' ? 'text-blue-400 border-blue-500/20 bg-blue-500/10' : 'text-red-400 border-red-500/20 bg-red-500/10'">{{ item.faction }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="loading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>
        <div v-else-if="filteredZoneGroups.length === 0 && !loading" class="text-center py-8 text-slate-500 text-sm">
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

const route = useRoute();
const loading = ref(true);
const items = ref([]);
const expansions = ref([]);
const zones = ref([]);
const total = ref(0);
const search = ref('');
const page = ref(1);
const itemsPerPage = 8;
const expandedZone = ref(null);

const activeExpansionSlug = computed(() => route.params.expansion || '');
const activeZoneSlug = computed(() => route.params.zone || '');

const activeExpansionName = computed(() => {
    const exp = expansions.value.find(e => e.slug === activeExpansionSlug.value);
    return exp?.name || '';
});

const activeZoneName = computed(() => {
    const z = zones.value.find(z => z.slug === activeZoneSlug.value);
    return z?.name || '';
});

const displayCount = computed(() => (activeExpansionSlug.value || activeZoneSlug.value) ? items.value.length : total.value);

const breadcrumbs = computed(() => {
    const crumbs = [
        { label: 'Base de données', to: '/base-de-donnees' },
    ];
    if (activeExpansionSlug.value) {
        crumbs.push({ label: 'Quêtes', to: '/base-de-donnees/quetes' });
        if (activeZoneSlug.value) {
            crumbs.push({ label: activeExpansionName.value, to: '/base-de-donnees/quetes/' + activeExpansionSlug.value });
            crumbs.push({ label: activeZoneName.value || activeZoneSlug.value });
        } else {
            crumbs.push({ label: activeExpansionName.value || activeExpansionSlug.value });
        }
    } else {
        crumbs.push({ label: 'Quêtes' });
    }
    return crumbs;
});

const zoneMap = computed(() => {
    const map = {};
    for (const item of items.value) {
        const zone = item.zone_name || '';
        if (!map[zone]) map[zone] = [];
        map[zone].push(item);
    }
    return map;
});

const filteredZoneGroups = computed(() => {
    const q = search.value.toLowerCase();
    return Object.entries(zoneMap.value)
        .map(([name, zoneItems]) => {
            const filtered = q ? zoneItems.filter(i => i.name_fr.toLowerCase().includes(q)) : zoneItems;
            return { name, items: filtered, count: filtered.length };
        })
        .filter(z => z.count > 0)
        .sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const filteredItems = computed(() => {
    const q = search.value.toLowerCase();
    return q ? items.value.filter(i => i.name_fr.toLowerCase().includes(q)) : items.value;
});

const paginatedZoneGroups = computed(() => {
    const start = (page.value - 1) * itemsPerPage;
    return filteredZoneGroups.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredZoneGroups.value.length / itemsPerPage)));

const toggleZone = (zone) => {
    expandedZone.value = expandedZone.value === zone.name ? null : zone.name;
};

async function fetchData() {
    loading.value = true;
    try {
        const params = {};
        if (activeExpansionSlug.value) params.expansion = activeExpansionSlug.value;
        if (activeZoneSlug.value) params.zone = activeZoneSlug.value;
        const { data } = await axios.get('/api/database/quests', { params });
        items.value = data.items;
        expansions.value = data.expansions;
        zones.value = data.zones || [];
        total.value = data.total;
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

watch(() => [route.params.expansion, route.params.zone], () => {
    page.value = 1;
    search.value = '';
    expandedZone.value = null;
    fetchData();
});

watch(search, () => {
    page.value = 1;
    expandedZone.value = null;
});

onMounted(fetchData);
</script>
