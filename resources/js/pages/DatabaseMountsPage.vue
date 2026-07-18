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
            title="Montures"
            :subtitle="activeCategoryName || 'Toutes les catégories'"
            :count="displayCount"
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

<script>
import AppLayout from '../layouts/AppLayout.vue';
import DatabaseLayout from '../layouts/DatabaseLayout.vue';

export default {
    layout: [AppLayout, DatabaseLayout],
};
</script>

<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import SearchFilter from '../components/SearchFilter.vue';
import CollectionIcon from '../components/CollectionIcon.vue';
import DatabasePageHeader from '../components/DatabasePageHeader.vue';

const props = defineProps({
    meta: { type: Object, required: true },
    category: { type: String, default: null },
    items: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
});

const loading = false;
const search = ref('');

const activeCategoryName = computed(() => {
    const cat = props.categories.find(c => c.slug === props.category);
    return cat?.name || '';
});

// Compteur affiché : nb d'éléments de la catégorie active, sinon total global.
const displayCount = computed(() => (props.category ? props.items.length : props.total));

const filteredItems = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return props.items;
    return props.items.filter(i => i.name_fr.toLowerCase().includes(q));
});
</script>
