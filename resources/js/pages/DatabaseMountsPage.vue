<template>
    <div class="space-y-6 py-6 sm:py-8">
        <DatabasePageHeader
            title="Montures"
            :subtitle="activeCategoryName || 'Toutes les catégories'"
            :count="total"
            count-label="montures"
            accent-color="amber"
        />

        <SearchFilter
            v-model:search="search"
            placeholder="Rechercher une monture..."
            :show-hide-toggle="false"
        />

        <!-- Flat table of all mounts -->
        <div v-if="filteredItems.length" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/10">
                        <th class="w-10 pb-3"></th>
                        <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3">Nom</th>
                        <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 hidden sm:table-cell">Source</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in filteredItems"
                        :key="item.id"
                        class="border-b border-white/3 even:bg-slate-800/20 hover:bg-slate-800/40 transition-colors"
                    >
                        <td class="py-2 pr-2">
                            <CollectionIcon :src="item.icon_url" :alt="item.name_fr" fallback="M" size="sm" class="text-amber-500" />
                        </td>
                        <td class="py-2">
                            <a :href="item.source_spell_id ? `https://www.wowhead.com/fr/spell=${item.source_spell_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(item.name_fr)}`" target="_blank" rel="noopener" class="text-slate-300 hover:text-amber-400 hover:underline">{{ item.name_fr }}</a>
                        </td>
                        <td class="py-2 text-slate-500 text-xs hidden sm:table-cell">{{ item.source || 'Inconnu' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-500 text-sm">Chargement...</div>
        <div v-else-if="filteredItems.length === 0 && !loading" class="text-center py-8 text-slate-500 text-sm">
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

const route = useRoute();
const loading = ref(true);
const items = ref([]);
const categories = ref([]);
const total = ref(0);
const search = ref('');

const activeSlug = computed(() => route.params.category || '');
const activeCategoryName = computed(() => {
    const cat = categories.value.find(c => c.slug === activeSlug.value);
    return cat?.name || '';
});

const filteredItems = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return items.value;
    return items.value.filter(i => i.name_fr.toLowerCase().includes(q));
});

async function fetchData() {
    loading.value = true;
    try {
        const params = {};
        if (activeSlug.value) params.category = activeSlug.value;
        const { data } = await axios.get('/api/database/mounts', { params });
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
    search.value = '';
    fetchData();
});

onMounted(fetchData);
</script>
