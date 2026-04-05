<template>
    <div class="space-y-6 py-6 sm:py-8">
        <DatabasePageHeader
            title="Hauts-faits"
            :subtitle="activeExpansionName || 'Toutes les extensions'"
            :count="total"
            count-label="hauts-faits"
            accent-color="amber"
        />

        <SearchFilter
            v-model:search="search"
            placeholder="Rechercher un haut-fait..."
            :show-hide-toggle="false"
            :debounce-ms="300"
            @search-debounced="onSearchDebounced"
        />

        <!-- Flat table of all achievements -->
        <div v-if="items.length" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/10">
                        <th class="w-10 pb-3"></th>
                        <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3">Nom</th>
                        <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 hidden sm:table-cell">Catégorie</th>
                        <th class="text-right text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 w-20">Points</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b border-white/3 even:bg-slate-800/20 hover:bg-slate-800/40 transition-colors"
                    >
                        <td class="py-2 pr-2">
                            <CollectionIcon :src="item.icon_url" :alt="item.name_fr" fallback="HF" size="sm" class="text-amber-500" />
                        </td>
                        <td class="py-2">
                            <a :href="`https://www.wowhead.com/fr/achievement=${item.id}`" target="_blank" rel="noopener" class="text-slate-300 hover:text-amber-400 hover:underline">{{ item.name_fr }}</a>
                        </td>
                        <td class="py-2 text-slate-500 text-xs hidden sm:table-cell">{{ item.category_name }}</td>
                        <td class="py-2 text-right text-[10px] font-mono text-slate-500">{{ item.points }} pts</td>
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
import CollectionIcon from '../components/CollectionIcon.vue';
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
        const { data } = await axios.get('/api/database/achievements', { params });
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
