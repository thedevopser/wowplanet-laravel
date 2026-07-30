<template>
    <div class="space-y-8 animate-in fade-in duration-500">
        <Head>
            <title>Mes personnages - WowPlanet</title>
        </Head>

        <div class="text-center max-w-3xl mx-auto">
            <h2 class="text-3xl md:text-4xl font-black mb-3">
                <span class="bg-clip-text text-transparent bg-linear-to-r from-blue-200 via-blue-400 to-blue-600">Mes personnages</span>
            </h2>
            <p class="text-slate-400 text-sm md:text-base">Cliquez sur un personnage pour voir sa progression</p>
        </div>

        <!-- Cross-character loading bar -->
        <div v-if="store.crossCharacterStatus === 'loading'" class="max-w-2xl mx-auto">
            <div class="flex items-center gap-3 bg-amber-500/10 border border-amber-500/20 rounded-xl px-4 py-2.5">
                <svg class="w-4 h-4 text-amber-400 animate-spin shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="text-xs text-amber-300/80">Chargement des donn&eacute;es multi-personnages...</span>
            </div>
        </div>
        <Transition enter-active-class="transition duration-300" enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition duration-500" leave-from-class="opacity-100" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="showCrossCharSuccess" class="max-w-2xl mx-auto">
            <div class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-4 py-2.5">
                <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-xs text-emerald-300/80">Donn&eacute;es multi-personnages charg&eacute;es</span>
            </div>
        </div>
        </Transition>

        <!-- Loading -->
        <LoadingSpinner
            v-if="store.loadingCharacters"
            icon="&#128100;"
            title="Chargement en cours..."
            subtitle="Récupération de vos personnages"
            hint="Cela peut prendre quelques instants selon le nombre de personnages..."
        />

        <template v-else-if="store.userCharacters.length">
            <!-- Search + Sort -->
            <div class="max-w-2xl mx-auto space-y-3">
                <div class="relative">
                    <input
                        v-model="characterSearch"
                        type="text"
                        placeholder="Rechercher un personnage..."
                        aria-label="Rechercher un personnage"
                        class="w-full bg-slate-800/80 border border-white/5 rounded-xl px-4 py-2.5 pl-10 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm md:text-base placeholder-slate-500 transition-all"
                    >
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <div class="flex items-center justify-center gap-1.5 flex-wrap">
                    <span class="text-xs text-slate-600 mr-1">Trier par :</span>
                    <button
                        v-for="opt in sortOptions"
                        :key="opt.key"
                        @click="sortBy = opt.key"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border',
                            sortBy === opt.key
                                ? 'bg-blue-600/20 border-blue-500/30 text-blue-400'
                                : 'bg-slate-800/50 border-white/5 text-slate-500 hover:text-slate-300'
                        ]"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- Favorites -->
            <section v-if="favoriteCharacters.length" class="space-y-3">
                <h3 class="flex items-center gap-2 text-sm font-bold text-amber-400/90 uppercase tracking-wide">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11c.08.2.27.34.49.36l5.52.44c.5.04.71.67.32 1l-4.2 3.6a.56.56 0 00-.19.57l1.28 5.39c.12.49-.41.88-.84.62l-4.73-2.89a.56.56 0 00-.58 0l-4.73 2.89c-.43.26-.96-.13-.84-.62l1.28-5.39a.56.56 0 00-.19-.57l-4.2-3.6c-.39-.33-.18-.96.32-1l5.52-.44a.56.56 0 00.49-.36L11.48 3.5z" />
                    </svg>
                    Favoris
                    <span class="text-slate-600 font-mono normal-case">{{ favoritesStore.favoriteCount }}/{{ MAX_FAVORITES }}</span>
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <MyCharacterCard
                        v-for="char in favoriteCharacters"
                        :key="char.name + '-' + char.realmSlug"
                        :character="char"
                        :is-favorite="true"
                        @toggle-favorite="toggleFavorite(char)"
                    />
                </div>
            </section>

            <!-- Character Grid -->
            <section v-if="filteredUserCharacters.length" class="space-y-3">
                <h3 v-if="favoritesStore.favoriteCount" class="text-sm font-bold text-slate-500 uppercase tracking-wide">
                    Tous mes personnages
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <MyCharacterCard
                        v-for="char in filteredUserCharacters"
                        :key="char.name + '-' + char.realmSlug"
                        :character="char"
                        :favorite-disabled="favoritesStore.isFull"
                        @toggle-favorite="toggleFavorite(char)"
                    />
                </div>
            </section>

            <!-- No search results -->
            <div v-if="!favoriteCharacters.length && !filteredUserCharacters.length" class="text-center py-16">
                <p class="text-slate-500">Aucun personnage ne correspond &agrave; votre recherche.</p>
            </div>
        </template>

        <!-- Empty state -->
        <div v-else class="text-center py-16 max-w-md mx-auto">
            <div class="w-16 h-16 mx-auto mb-6 bg-slate-800/60 border border-white/10 rounded-2xl flex items-center justify-center">
                <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-300 mb-2">Aucun personnage trouvé</h3>
            <p class="text-sm text-slate-500 mb-6">Connectez-vous avec Battle.net pour importer automatiquement tous vos personnages World of Warcraft.</p>
            <a
                href="/auth/blizzard/redirect"
                class="btn-gradient text-white font-semibold px-6 py-2.5 rounded-lg text-sm shadow-lg shadow-blue-500/20 inline-block"
            >
                Se connecter avec Battle.net
            </a>
        </div>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useCharacterStore } from '../stores/character';
import { useFavoriteStore, MAX_FAVORITES } from '../stores/favorites';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import MyCharacterCard from '../components/MyCharacterCard.vue';

const store = useCharacterStore();
const favoritesStore = useFavoriteStore();
const characterSearch = ref('');
const sortBy = ref('name');
const showCrossCharSuccess = ref(false);

const sortOptions = [
    { key: 'name', label: 'Nom' },
    { key: 'level', label: 'Niveau' },
    { key: 'class', label: 'Classe' },
    { key: 'realm', label: 'Royaume' },
];

onMounted(async () => {
    if (!store.userCharacters.length) {
        await store.fetchUserCharacters();
    }
    if (store.isAuthenticated && store.crossCharacterStatus !== 'ready') {
        store.computeCrossCharacter();
    }
    if (store.isAuthenticated) {
        favoritesStore.fetchFavorites();
    }
});

watch(() => store.crossCharacterStatus, (status) => {
    if (status === 'ready') {
        showCrossCharSuccess.value = true;
        setTimeout(() => { showCrossCharSuccess.value = false; }, 3000);
    }
});

const matchesSearch = (char) => {
    const q = characterSearch.value.toLowerCase().trim();
    if (!q) return true;
    return char.name.toLowerCase().includes(q) ||
        char.realm.toLowerCase().includes(q) ||
        char.className.toLowerCase().includes(q) ||
        char.raceName.toLowerCase().includes(q) ||
        char.faction.toLowerCase().includes(q);
};

const characterKey = (char) => `${char.realmSlug.toLowerCase()}|${char.name.toLowerCase()}`;

// Favorites keep their own order (order they were starred), not the current sort.
const favoriteCharacters = computed(() => {
    const keys = favoritesStore.favoriteKeys;
    if (!keys.size) return [];
    const byKey = new Map(store.userCharacters.map(c => [characterKey(c), c]));
    return favoritesStore.favorites
        .map(f => byKey.get(`${f.realm_slug}|${f.character_name}`))
        .filter(c => c !== undefined && matchesSearch(c));
});

const toggleFavorite = (char) => favoritesStore.toggleFavorite(char.realmSlug, char.name);

const filteredUserCharacters = computed(() => {
    const keys = favoritesStore.favoriteKeys;
    const list = store.userCharacters.filter(c => !keys.has(characterKey(c)) && matchesSearch(c));
    return [...list].sort((a, b) => {
        switch (sortBy.value) {
            case 'level': return b.level - a.level;
            case 'class': return a.className.localeCompare(b.className);
            case 'realm': return a.realm.localeCompare(b.realm);
            default: return a.name.localeCompare(b.name);
        }
    });
});
</script>
