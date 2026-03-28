<template>
    <div class="space-y-6">
        <!-- Category selector tabs -->
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-3">
            <button
                v-for="cat in categories"
                :key="cat.name"
                @click="activeCategory = cat.name"
                :class="[
                    'px-3 sm:px-4 py-2 sm:py-3 rounded-xl sm:rounded-2xl text-[11px] sm:text-[13px] font-bold transition-all border flex flex-col items-center gap-1 group relative overflow-hidden',
                    activeCategory === cat.name
                        ? 'bg-blue-600 border-blue-400 text-white shadow-xl shadow-blue-500/20 scale-105 z-10'
                        : 'bg-slate-800/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-700 hover:border-white/10'
                ]"
            >
                <span class="relative z-10">{{ translateCategory(cat.name) }}</span>
                <div :class="[
                    'text-[9px] font-mono px-2 py-0.5 rounded-full border relative z-10',
                    activeCategory === cat.name ? 'bg-blue-700/50 border-white/20' : 'bg-slate-800 border-white/5 opacity-60'
                ]">
                    {{ cat.completed }} / {{ cat.total }}
                </div>
                <div class="absolute bottom-0 left-0 h-0.5 bg-white/20 transition-all duration-500" :style="{ width: cat.total > 0 ? (cat.completed / cat.total * 100) + '%' : '0%' }"></div>
            </button>
        </div>

        <!-- Header card -->
        <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/5 blur-3xl -mr-16 -mt-16"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-end mb-4 sm:mb-6">
                    <div>
                        <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                            <div class="w-2 h-6 sm:h-8 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50"></div>
                            Mascottes
                        </h3>
                        <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">{{ translateCategory(activeCategory) }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl sm:text-3xl font-black text-blue-400 font-mono">
                            {{ Math.round(activeCategoryData.completed / (activeCategoryData.total || 1) * 100) }}%
                        </div>
                        <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">
                            {{ activeCategoryData.completed }} / {{ activeCategoryData.total }}
                        </div>
                    </div>
                </div>
                <div class="h-3 bg-slate-800 rounded-full overflow-hidden border border-white/5">
                    <div class="h-full bg-linear-to-r from-blue-700 via-blue-500 to-blue-400 transition-all duration-1000 relative shadow-[0_0_15px_rgba(59,130,246,0.3)]" :style="{ width: progressPercent + '%' }">
                        <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                    </div>
                </div>
            </div>
        </div>

        <SearchFilter
            v-model:search="search"
            v-model:hideCompleted="hideCompleted"
            placeholder="Rechercher une mascotte..."
            hideLabel="Masquer obtenues"
        />

        <!-- Source subcategories -->
        <section v-if="filteredSources.length">
            <div class="flex justify-between items-center mb-4 sm:mb-6">
                <h4 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                    Sources
                    <div class="flex-1 h-px bg-slate-700"></div>
                </h4>
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
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-sm md:text-base font-bold text-slate-300 group-hover:text-blue-400 transition-colors">{{ translateSource(src.name) }}</span>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] sm:text-xs font-mono text-slate-500">{{ src.completed }}/{{ src.total }}</span>
                            <svg class="w-4 h-4 text-slate-600 transition-transform duration-200" :class="expandedSource === src.name ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
                    <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-600/80 transition-all duration-700" :style="{ width: (src.completed / src.total * 100) + '%' }"></div>
                    </div>
                    <div v-if="expandedSource === src.name" class="mt-4 pt-4 border-t border-white/5 space-y-1 max-h-96 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                        <div v-for="item in sortedItems(src.items)" :key="item.id" class="flex items-center gap-3 text-xs sm:text-sm py-1.5">
                            <CollectionIcon :src="item.icon_url" :alt="item.name" fallback="P" size="sm" class="text-blue-500" />
                            <a :href="item.wowhead_id ? `https://www.wowhead.com/fr/npc=${item.wowhead_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(item.name)}`" target="_blank" rel="noopener" @click.stop :class="[item.is_completed ? 'text-blue-400 font-medium' : 'text-slate-500', 'hover:underline flex-1 truncate']">{{ item.name }}</a>
                            <span v-if="item.is_completed" class="text-green-500 font-bold shrink-0">&check;</span>
                            <span v-else class="text-slate-800 shrink-0">&cir;</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Uncategorized items (items without category/source) -->
        <section v-if="uncategorizedItems.length && activeCategory === 'Non classé'">
            <h4 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1 mb-4">
                Mascottes non classées
                <div class="flex-1 h-px bg-slate-700"></div>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <a v-for="item in uncategorizedItems" :key="item.id" :href="item.wowhead_id ? `https://www.wowhead.com/fr/npc=${item.wowhead_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(item.name)}`" target="_blank" rel="noopener" class="flex items-center gap-3 p-3 sm:p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-blue-500/30 transition-all">
                    <CollectionIcon :src="item.icon_url" :alt="item.name" fallback="P" size="lg" class="text-blue-500 group-hover:scale-110 transition-transform shadow-lg shadow-blue-500/5" />
                    <div class="flex-1 min-w-0">
                        <div class="text-sm md:text-base font-bold text-slate-200 group-hover:text-blue-400 transition-colors truncate">{{ item.name }}</div>
                        <div class="text-[10px] sm:text-xs text-slate-500 font-mono">ID: {{ item.id }}</div>
                    </div>
                    <div v-if="item.is_completed" class="px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black uppercase bg-green-500/10 text-green-400 border border-green-500/20 shrink-0">Obtenue</div>
                </a>
            </div>
        </section>

        <div v-if="filteredSources.length === 0 && (activeCategory !== 'Non classé' || uncategorizedItems.length === 0)" class="text-center py-8 text-slate-500 text-sm">
            Aucun résultat trouvé.
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import SearchFilter from './SearchFilter.vue';
import CollectionIcon from './CollectionIcon.vue';
import { useCharacterStore } from '../stores/character';
import { useWowheadTooltips } from '../composables/useWowheadTooltips';

useWowheadTooltips();
const characterStore = useCharacterStore();

const props = defineProps({
    character: { type: Object, required: true },
});

const page = ref(1);
const itemsPerPage = 8;
const search = ref('');
const hideCompleted = ref(false);
const expandedSource = ref(null);

const allPets = computed(() => props.character.pets || []);

/** French translations for non-expansion category names */
const CATEGORY_FR = {
    'Pets': 'Mascottes',
    'Promotion': 'Promotion',
    'World Events': 'Événements mondiaux',
    'PVP': 'JcJ',
    'Non classé': 'Non classé',
};

const translateCategory = (name) => CATEGORY_FR[name] || name;

/** French translations for source/subcategory names */
const SOURCE_FR = {
    // Sources générales
    'Achievement': 'Haut-fait',
    'Quest': 'Quête',
    'Vendor': 'Vendeur',
    'Raid Drop': 'Butin de raid',
    'Dungeon Drop': 'Butin de donjon',
    'Drop': 'Butin',
    'Zone Drop': 'Butin de zone',
    'Reputation': 'Réputation',
    'Paragon Reputation': 'Réputation parangon',
    'Treasure': 'Trésor',
    'Rare Spawn': 'Rare',
    'Rare': 'Rare',
    'World Boss': 'Boss mondial',
    'Renown': 'Renom',
    'Collect': 'Collection',
    'Zone Feature': 'Activité de zone',
    'Campaign': 'Campagne',
    'Prey': 'Proie',
    'Delves': 'Gouffres',
    'Profession': 'Métier',
    'Fishing': 'Pêche',
    'Archaeology': 'Archéologie',
    'Engineering': 'Ingénierie',
    'Cooking': 'Cuisine',
    'Pet Battle': 'Combat de mascottes',
    'Wild Pet': 'Mascotte sauvage',
    'Wild': 'Sauvage',
    'Tamer': 'Maître mascotte',
    'Trainer': 'Dresseur',
    'TCG/AH': 'JCC/HV',
    'Trading Card Game / Auction House': 'JCC / Hôtel des ventes',
    'Blizzard Store': 'Boutique Blizzard',
    'Promotions': 'Promotions',
    'Blizzcon': 'BlizzCon',
    'Collector\'s Edition': 'Édition collector',
    'Recruit-A-Friend': 'Parrainage',
    'Twitch Drops': 'Drops Twitch',
    // Événements mondiaux
    'Brewfest': 'Fête des Brasseurs',
    'Hallow\'s End': 'Sanssaint',
    'Love is in the Air': 'De l\'amour dans l\'air',
    'Noblegarden': 'Le jardin noble',
    'Winter Veil': 'Voile d\'hiver',
    'Lunar Festival': 'Fête lunaire',
    'Midsummer Festival': 'Solstice d\'été',
    'Darkmoon Faire': 'Foire de Sombrelune',
    'Children\'s Week': 'Semaine des enfants',
    'Day of the Dead': 'Jour des morts',
    'Pilgrim\'s Bounty': 'Bienfaits du pèlerin',
    'Timewalking': 'Marche du temps',
    'Secrets of Azeroth': 'Secrets d\'Azeroth',
    'Anniversary': 'Anniversaire',
    // Shadowlands
    'Torghast': 'Tourment',
    'Adventures': 'Aventures',
    'Covenant Feature': 'Fonctionnalité de congrégation',
    'Protoform Synthesis': 'Synthèse de protoforme',
    // Warlords of Draenor
    'Garrison': 'Fief',
    'Missions': 'Missions',
    // Mists of Pandaria
    'Island': 'Île',
    'Primal Eggs': 'Œufs primordiaux',
    // PVP
    'Honor': 'Honneur',
    'Gladiator': 'Gladiateur',
};

const translateSource = (name) => {
    if (SOURCE_FR[name]) return SOURCE_FR[name];
    if (name.startsWith('Renown: ')) return 'Renom : ' + name.slice(8);
    return name;
};

/** Build category → source → items structure from flat pets array */
const categoryMap = computed(() => {
    const map = {};

    for (const item of allPets.value) {
        const cat = item.category || null;
        const src = item.source || null;

        if (cat && src) {
            if (!map[cat]) map[cat] = {};
            if (!map[cat][src]) map[cat][src] = [];
            map[cat][src].push(item);
        }
    }

    return map;
});

/** Ordered list of category names (recent expansions first, then special categories) */
const EXTRA_CATEGORIES = ['Pets', 'World Events', 'PVP', 'Promotion'];
const categoryOrder = computed(() => [...characterStore.expansionNamesDesc, ...EXTRA_CATEGORIES]);

const categories = computed(() => {
    const cats = [];
    const knownCats = new Set(Object.keys(categoryMap.value));

    for (const name of categoryOrder.value) {
        if (knownCats.has(name)) {
            const sources = categoryMap.value[name];
            const items = Object.values(sources).flat();
            cats.push({
                name,
                total: items.length,
                completed: items.filter(i => i.is_completed).length,
            });
        }
    }

    for (const name of knownCats) {
        if (!categoryOrder.value.includes(name)) {
            const sources = categoryMap.value[name];
            const items = Object.values(sources).flat();
            cats.push({
                name,
                total: items.length,
                completed: items.filter(i => i.is_completed).length,
            });
        }
    }

    const uncatCount = allPets.value.filter(i => !i.category || !i.source).length;
    if (uncatCount > 0) {
        cats.push({
            name: 'Non classé',
            total: uncatCount,
            completed: allPets.value.filter(i => (!i.category || !i.source) && i.is_completed).length,
        });
    }

    return cats;
});

const activeCategory = ref('');
watch(categories, (cats) => {
    if (cats.length > 0 && !cats.find(c => c.name === activeCategory.value)) {
        activeCategory.value = cats[0].name;
    }
}, { immediate: true });

const activeCategoryData = computed(() => {
    return categories.value.find(c => c.name === activeCategory.value) || { total: 0, completed: 0 };
});

const progressPercent = computed(() => {
    const { completed, total } = activeCategoryData.value;
    return total > 0 ? completed / total * 100 : 0;
});

const sourcesForCategory = computed(() => {
    if (activeCategory.value === 'Non classé') return [];
    const sources = categoryMap.value[activeCategory.value] || {};

    return Object.entries(sources).map(([name, items]) => ({
        name,
        items,
        total: items.length,
        completed: items.filter(i => i.is_completed).length,
    })).sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const filteredSources = computed(() => {
    if (!search.value && !hideCompleted.value) return sourcesForCategory.value;

    const q = search.value.toLowerCase();
    return sourcesForCategory.value.map(src => {
        let items = src.items;
        if (search.value) {
            items = items.filter(i => i.name.toLowerCase().includes(q));
        }
        if (hideCompleted.value) {
            items = items.filter(i => !i.is_completed);
        }
        return { ...src, items, total: items.length, completed: items.filter(i => i.is_completed).length };
    }).filter(src => src.items.length > 0);
});

const paginatedSources = computed(() => {
    const start = (page.value - 1) * itemsPerPage;
    return filteredSources.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil(filteredSources.value.length / itemsPerPage));

const uncategorizedItems = computed(() => {
    let items = allPets.value.filter(i => !i.category || !i.source);
    if (search.value) {
        const q = search.value.toLowerCase();
        items = items.filter(i => i.name.toLowerCase().includes(q));
    }
    if (hideCompleted.value) {
        items = items.filter(i => !i.is_completed);
    }
    return [...items].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const toggleSource = (src) => {
    expandedSource.value = expandedSource.value === src.name ? null : src.name;
};

const sortedItems = (items) => [...items].sort((a, b) => a.name.localeCompare(b.name, 'fr'));

watch(activeCategory, () => {
    page.value = 1;
    expandedSource.value = null;
});

watch([search, hideCompleted], () => {
    page.value = 1;
    expandedSource.value = null;
});
</script>
