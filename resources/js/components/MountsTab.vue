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
                        ? 'bg-amber-600 border-amber-400 text-white shadow-xl shadow-amber-500/20 scale-105 z-10'
                        : 'bg-slate-800/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-700 hover:border-white/10'
                ]"
            >
                <span class="relative z-10">{{ translateCategory(cat.name) }}</span>
                <div :class="[
                    'text-[9px] font-mono px-2 py-0.5 rounded-full border relative z-10',
                    activeCategory === cat.name ? 'bg-amber-700/50 border-white/20' : 'bg-slate-800 border-white/5 opacity-60'
                ]">
                    {{ cat.completed }} / {{ cat.total }}
                </div>
                <div class="absolute bottom-0 left-0 h-0.5 bg-white/20 transition-all duration-500" :style="{ width: cat.total > 0 ? (cat.completed / cat.total * 100) + '%' : '0%' }"></div>
            </button>
        </div>

        <!-- Header card -->
        <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-amber-600/5 blur-3xl -mr-16 -mt-16"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-end mb-4 sm:mb-6">
                    <div>
                        <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                            <div class="w-2 h-6 sm:h-8 bg-amber-500 rounded-full shadow-lg shadow-amber-500/50"></div>
                            Montures
                        </h3>
                        <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">{{ translateCategory(activeCategory) }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl sm:text-3xl font-black text-amber-400 font-mono">
                            {{ Math.round(activeCategoryData.completed / (activeCategoryData.total || 1) * 100) }}%
                        </div>
                        <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">
                            {{ activeCategoryData.completed }} / {{ activeCategoryData.total }}
                        </div>
                    </div>
                </div>
                <div class="h-3 bg-slate-800 rounded-full overflow-hidden border border-white/5">
                    <div class="h-full bg-linear-to-r from-amber-700 via-amber-500 to-amber-400 transition-all duration-1000 relative shadow-[0_0_15px_rgba(245,158,11,0.3)]" :style="{ width: progressPercent + '%' }">
                        <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                    </div>
                </div>
            </div>
        </div>

        <SearchFilter
            v-model:search="search"
            v-model:hideCompleted="hideCompleted"
            placeholder="Rechercher une monture..."
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
                        <span class="text-sm md:text-base font-bold text-slate-300 group-hover:text-amber-400 transition-colors">{{ translateSource(src.name) }}</span>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] sm:text-xs font-mono text-slate-500">{{ src.completed }}/{{ src.total }}</span>
                            <svg class="w-4 h-4 text-slate-600 transition-transform duration-200" :class="expandedSource === src.name ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
                    <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full bg-amber-600/80 transition-all duration-700" :style="{ width: (src.completed / src.total * 100) + '%' }"></div>
                    </div>
                    <div v-if="expandedSource === src.name" class="mt-4 pt-4 border-t border-white/5 space-y-1 max-h-96 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                        <div v-for="item in sortedItems(src.items)" :key="item.id" class="flex items-center gap-3 text-xs sm:text-sm py-1.5">
                            <CollectionIcon :src="item.icon_url" :alt="item.name" fallback="M" size="sm" class="text-amber-500" />
                            <a :href="item.wowhead_id ? `https://www.wowhead.com/fr/spell=${item.wowhead_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(item.name)}`" target="_blank" rel="noopener" @click.stop :class="[item.is_completed ? 'text-amber-400 font-medium' : 'text-slate-500', 'hover:underline flex-1 truncate']">{{ item.name }}</a>
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
                Montures non classées
                <div class="flex-1 h-px bg-slate-700"></div>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <a v-for="item in uncategorizedItems" :key="item.id" :href="item.wowhead_id ? `https://www.wowhead.com/fr/spell=${item.wowhead_id}` : `https://www.wowhead.com/fr/search?q=${encodeURIComponent(item.name)}`" target="_blank" rel="noopener" class="flex items-center gap-3 p-3 sm:p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-amber-500/30 transition-all">
                    <CollectionIcon :src="item.icon_url" :alt="item.name" fallback="M" size="lg" class="text-amber-500 group-hover:scale-110 transition-transform shadow-lg shadow-amber-500/5" />
                    <div class="flex-1 min-w-0">
                        <div class="text-sm md:text-base font-bold text-slate-200 group-hover:text-amber-400 transition-colors truncate">{{ item.name }}</div>
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

const characterStore = useCharacterStore();

const props = defineProps({
    character: { type: Object, required: true },
});

const page = ref(1);
const itemsPerPage = 8;
const search = ref('');
const hideCompleted = ref(false);
const expandedSource = ref(null);

const allMounts = computed(() => props.character.mounts || []);

/** French translations for non-expansion category names */
const CATEGORY_FR = {
    'Mounts': 'Montures',
    'Limited Time': 'Durée limitée',
    'Past Limited Time': 'Ancien durée limitée',
    'Racial': 'Raciales',
    'Professions': 'Métiers',
    'PVP': 'JcJ',
    'World Events': 'Événements mondiaux',
    'Promotion': 'Promotion',
    'Other': 'Autre',
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
    'Raid Renown': 'Renom de raid',
    'Riddle': 'Énigme',
    'Daily Activities': 'Activités journalières',
    'Zone Feature': 'Activité de zone',
    'Campaign': 'Campagne',
    'Prey': 'Proie',
    'Allied Race': 'Race alliée',
    'Allied Races': 'Races alliées',
    'Pre-launch Event': 'Événement de pré-lancement',
    'Pre-launch event': 'Événement de pré-lancement',
    'Zone': 'Zone',
    // Mounts (collection)
    'Collect': 'Collection',
    'Reputations': 'Réputations',
    'Toys': 'Jouets',
    'Heirlooms': 'Héritages',
    // Durée limitée
    'Promotions': 'Promotions',
    'Blizzard Store': 'Boutique Blizzard',
    'Midnight Pre-Patch': 'Pré-patch Midnight',
    // Midnight
    'Delves': 'Gouffres',
    // The War Within
    'Visions Revisited': 'Visions revisitées',
    // Dragonflight
    'Obsidian Citadel': 'Citadelle d\'obsidienne',
    'Zskera Vaults': 'Caveaux de Zskera',
    'Events': 'Événements',
    'Time Rifts': 'Failles temporelles',
    'Dream Infusion': 'Infusion de rêve',
    'Emerald Bounty': 'Prime d\'émeraude',
    'Archives': 'Archives',
    // Shadowlands
    'Torghast': 'Tourment',
    'Adventures': 'Aventures',
    'Tormentors': 'Tourmenteurs',
    'Maw Assaults': 'Assauts de l\'Antre',
    'Maldraxxus Callings': 'Appels de Maldraxxus',
    'Covenant Feature': 'Fonctionnalité de congrégation',
    'Protoform Synthesis': 'Synthèse de protoforme',
    // Covenants Shadowlands
    'Night Fae Quest': 'Quête Faë nocturne',
    'Night Fae Renown': 'Renom Faë nocturne',
    'Night Fae Vendor': 'Vendeur Faë nocturne',
    'Night Fae Covenant Features': 'Fonctionnalités Faë nocturne',
    'Night Fae Rare Spawn': 'Rare Faë nocturne',
    'Kyrian Quest': 'Quête Kyrien',
    'Kyrian Renown': 'Renom Kyrien',
    'Kyrian Vendor': 'Vendeur Kyrien',
    'Kyrian Covenant Features': 'Fonctionnalités Kyrien',
    'Kyrian Rare Spawn': 'Rare Kyrien',
    'Necrolord Quest': 'Quête Nécro-seigneur',
    'Necrolord Renown': 'Renom Nécro-seigneur',
    'Necrolord Vendor': 'Vendeur Nécro-seigneur',
    'Necrolord Covenant Features': 'Fonctionnalités Nécro-seigneur',
    'Necrolord Rare Spawn': 'Rare Nécro-seigneur',
    'Venthyr Quest': 'Quête Venthyr',
    'Venthyr Renown': 'Renom Venthyr',
    'Venthyr Vendor': 'Vendeur Venthyr',
    'Venthyr Covenant Features': 'Fonctionnalités Venthyr',
    'Venthyr Rare Spawn': 'Rare Venthyr',
    // Battle for Azeroth
    'Medals': 'Médailles',
    'Tinkering': 'Bricolage',
    'Dubloons': 'Doublons',
    'Visions': 'Visions',
    'Island Expedition': 'Expédition insulaire',
    'Warfront: Arathi': 'Front de guerre : Arathi',
    'Warfront: Darkshore': 'Front de guerre : Sombrivage',
    'Assault: Vale of Eternal Blossoms': 'Assaut : Val de l\'Éternel printemps',
    'Assault: Uldum': 'Assaut : Uldum',
    // Legion
    'Mage Tower': 'Tour des mages',
    'Class Hall': 'Domaine de classe',
    // Warlords of Draenor
    'Garrison': 'Fief',
    'Missions': 'Missions',
    'Stables': 'Écuries',
    'Trading Post': 'Comptoir commercial',
    'Fishing Shack': 'Cabane de pêche',
    // Mists of Pandaria
    'Challenge Mode': 'Mode défi',
    'Golden Lotus': 'Lotus doré',
    'Order of the Cloud Serpent': 'Ordre du Serpent-nuage',
    'Shado-Pan': 'Ombrepan',
    'Kun-Lai Vendor': 'Vendeur de Kun-Lai',
    'The Tillers': 'Les Laboureurs',
    'Primal Eggs': 'Œufs primordiaux',
    // Wrath of the Lich King
    'Argent Tournament': 'Tournoi d\'argent',
    // The Burning Crusade
    'Cenarion Expedition': 'Expédition cénarienne',
    'Kurenai/The Mag\'har': 'Kurenai / Mag\'har',
    'Netherwing': 'Aile-du-Néant',
    'Sha\'tari Skyguard': 'Garde-ciel sha\'tari',
    // Raciales
    'Human': 'Humain',
    'Dwarf': 'Nain',
    'Night Elf': 'Elfe de la nuit',
    'Gnome': 'Gnome',
    'Draenei': 'Draeneï',
    'Worgen': 'Worgen',
    'Pandaren': 'Pandaren',
    'Dracthyr': 'Dracthyr',
    'Orc': 'Orc',
    'Undead': 'Mort-vivant',
    'Tauren': 'Tauren',
    'Troll': 'Troll',
    'Goblin': 'Gobelin',
    'Blood Elf': 'Elfe de sang',
    // Professions
    'Alchemy': 'Alchimie',
    'Archaeology': 'Archéologie',
    'Engineering': 'Ingénierie',
    'Fishing': 'Pêche',
    'Jewelcrafting': 'Joaillerie',
    'Tailoring': 'Couture',
    'Leatherworking': 'Travail du cuir',
    'Blacksmith': 'Forge',
    // PVP
    'Mark of Honor': 'Marque d\'honneur',
    'Honor': 'Honneur',
    'Halaa': 'Halaa',
    'Timeless Isle': 'Île du temps figé',
    'Ashran': 'Ashran',
    'Vicious Saddle': 'Selle vicieuse',
    'Gladiator': 'Gladiateur',
    'Talon\'s Vengeance': 'Vengeance de la Serre',
    // Événements mondiaux
    'Brewfest': 'Fête des Brasseurs',
    'Hallow\'s End': 'Sanssaint',
    'Love is in the Air': 'De l\'amour dans l\'air',
    'Noblegarden': 'Le jardin noble',
    'Winter Veil': 'Voile d\'hiver',
    'Lunar Festival': 'Fête lunaire',
    'Brawler\'s Guild': 'Guilde des bagarreurs',
    'Darkmoon Faire': 'Foire de Sombrelune',
    'Timewalking': 'Marche du temps',
    'Secrets of Azeroth': 'Secrets d\'Azeroth',
    'Anniversary': 'Anniversaire',
    // Ancien durée limitée
    'Trading Post Re-Releases': 'Rééditions du Comptoir',
    'Trading Post Originals': 'Originaux du Comptoir',
    'Plunderstorm': 'Plunderstorm',
    'Dastardly Duos': 'Duos infâmes',
    'Greedy Emissary': 'Émissaire cupide',
    'Remix: Pandaria': 'Remix : Pandaria',
    'Remix: Legion': 'Remix : Légion',
    // Promotion
    'Blizzcon': 'BlizzCon',
    'Player Vote': 'Vote des joueurs',
    'Collector\'s Edition': 'Édition collector',
    'WoW Classic': 'WoW Classic',
    'Blizzard Anniversary': 'Anniversaire Blizzard',
    '20th Anniversary': '20e anniversaire',
    'HotS': 'HotS',
    'Hearthstone': 'Hearthstone',
    'Warcraft III Reforged': 'Warcraft III Reforged',
    'Diablo IV': 'Diablo IV',
    'Mountain Dew': 'Mountain Dew',
    'Razer': 'Razer',
    'Recruit-A-Friend': 'Parrainage',
    'Azeroth Choppers': 'Azeroth Choppers',
    'Trading Card Game / Auction House': 'Jeu de cartes / Hôtel des ventes',
    'Annual Subscription': 'Abonnement annuel',
    'Twitch Drops': 'Drops Twitch',
    // Autre
    'Paladin': 'Paladin',
    'Demon Hunter': 'Chasseur de démons',
    'Warlock': 'Démoniste',
    'Death Knight': 'Chevalier de la mort',
    'Guild Vendor': 'Vendeur de guilde',
    'BMAH': 'HNE',
    'Feats of Strength': 'Exploits',
    'Old School Ride': 'Monture classique',
    'Make-A-Wish': 'Make-A-Wish',
    'Unknown': 'Inconnu',
};

const translateSource = (name) => {
    if (SOURCE_FR[name]) return SOURCE_FR[name];
    if (name.startsWith('Renown: ')) return 'Renom : ' + name.slice(8);
    if (name.startsWith('Trading Post: ')) return 'Comptoir : ' + name.slice(14);
    if (name.startsWith('War Within: ')) return 'The War Within : ' + name.slice(12);
    if (name.startsWith('Midnight: ')) return 'Midnight : ' + name.slice(10);
    return name;
};

/** Build category → source → items structure from flat mounts array */
const categoryMap = computed(() => {
    const map = {};

    for (const item of allMounts.value) {
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
const EXTRA_CATEGORIES = ['Mounts', 'Racial', 'Professions', 'PVP', 'World Events', 'Limited Time', 'Past Limited Time', 'Promotion', 'Other'];
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

    const uncatCount = allMounts.value.filter(i => !i.category || !i.source).length;
    if (uncatCount > 0) {
        cats.push({
            name: 'Non classé',
            total: uncatCount,
            completed: allMounts.value.filter(i => (!i.category || !i.source) && i.is_completed).length,
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
    let items = allMounts.value.filter(i => !i.category || !i.source);
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
