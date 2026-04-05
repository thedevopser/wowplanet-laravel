<template>
    <div class="space-y-6 py-6 sm:py-8">
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
            :current-page="currentPage"
            :last-page="lastPage"
            :total="total"
            @page-change="onPageChange"
        />

        <div v-if="loading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>
        <div v-else-if="items.length === 0 && !loading" class="text-center py-8 text-slate-500 text-sm">
            Aucun résultat trouvé.
        </div>

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
const loading = ref(true);
const items = ref([]);
const expansions = ref([]);
const total = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const search = ref('');
const serverSearch = ref('');

const activeSlug = computed(() => route.params.expansion || '');

const activeExpansionName = computed(() => {
    const exp = expansions.value.find(e => e.slug === activeSlug.value);
    return exp?.name || '';
});

async function fetchData() {
    loading.value = true;
    try {
        const params = { page: currentPage.value };
        if (activeSlug.value) params.expansion = activeSlug.value;
        if (serverSearch.value) params.search = serverSearch.value;
        const { data } = await axios.get('/api/database/quests', { params });
        items.value = data.items;
        expansions.value = data.expansions;
        total.value = data.total;
        lastPage.value = data.last_page;
        currentPage.value = data.current_page;
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

function onPageChange(page) {
    currentPage.value = page;
    fetchData();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function onSearchDebounced(value) {
    serverSearch.value = value;
    currentPage.value = 1;
    fetchData();
}

watch(() => route.params.expansion, () => {
    currentPage.value = 1;
    search.value = '';
    serverSearch.value = '';
    fetchData();
});

onMounted(fetchData);
</script>
