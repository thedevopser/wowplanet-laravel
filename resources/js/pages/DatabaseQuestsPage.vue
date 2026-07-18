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

        <DatabasePageHeader
            title="Quêtes"
            :subtitle="activeExpansionName || 'Toutes les extensions'"
            :count="total"
            count-label="quêtes"
            accent-color="blue"
        />

        <SearchFilter
            v-model:search="search"
            placeholder="Rechercher une quête..."
            :show-hide-toggle="false"
            :debounce-ms="300"
            @search-debounced="onSearchDebounced"
        />

        <!-- Flat table of all quests -->
        <div v-if="items.length" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/10">
                        <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3">Nom</th>
                        <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 hidden sm:table-cell">Zone</th>
                        <th class="text-right text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 w-24 hidden sm:table-cell">Faction</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b border-white/3 even:bg-slate-800/20 hover:bg-slate-800/40 transition-colors"
                    >
                        <td class="py-2">
                            <a :href="`https://www.wowhead.com/fr/quest=${item.id}`" target="_blank" rel="noopener" class="text-slate-300 hover:text-blue-400 hover:underline">{{ item.name_fr }}</a>
                        </td>
                        <td class="py-2 text-slate-500 text-xs hidden sm:table-cell">{{ item.zone_name }}</td>
                        <td class="py-2 text-right hidden sm:table-cell">
                            <span
                                v-if="item.faction"
                                class="text-[9px] font-mono px-1.5 py-0.5 rounded border"
                                :class="item.faction === 'Alliance'
                                    ? 'text-blue-400 border-blue-500/20 bg-blue-500/10'
                                    : 'text-red-400 border-red-500/20 bg-red-500/10'"
                            >{{ item.faction }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <DatabasePagination
            :current-page="current_page"
            :last-page="last_page"
            :total="total"
            @page-change="onPageChange"
        />

        <div v-if="loading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>
        <div v-else-if="items.length === 0 && !loading" class="text-center py-8 text-slate-500 text-sm">
            Aucun résultat trouvé.
        </div>

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
import { Head, router, usePage } from '@inertiajs/vue3';
import SearchFilter from '../components/SearchFilter.vue';
import DatabasePageHeader from '../components/DatabasePageHeader.vue';
import DatabasePagination from '../components/DatabasePagination.vue';

const props = defineProps({
    meta: { type: Object, required: true },
    expansion: { type: String, default: null },
    search: { type: String, default: null },
    items: { type: Array, default: () => [] },
    expansions: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    current_page: { type: Number, default: 1 },
    last_page: { type: Number, default: 1 },
});

const page = usePage();
const loading = false;

const search = ref(props.search ?? '');

const activeExpansionName = computed(() => {
    const exp = props.expansions.find(e => e.slug === props.expansion);
    return exp?.name || '';
});

const dataOnly = ['items', 'expansions', 'total', 'current_page', 'last_page', 'search'];

function basePath() {
    return page.url.split('?')[0];
}

function onPageChange(newPage) {
    router.get(basePath(), { page: newPage, search: search.value || undefined }, {
        preserveState: true,
        only: dataOnly,
    });
}

function onSearchDebounced(value) {
    router.get(basePath(), { page: 1, search: value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        only: dataOnly,
    });
}
</script>
