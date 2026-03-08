<template>
    <div class="space-y-8 animate-in fade-in duration-500">
        <div class="text-center max-w-3xl mx-auto">
            <h2 class="text-3xl md:text-4xl font-black mb-3">
                <span class="bg-clip-text text-transparent bg-linear-to-r from-blue-200 via-blue-400 to-blue-600">Mes personnages</span>
            </h2>
            <p class="text-slate-400 text-sm md:text-base">Cliquez sur un personnage pour voir sa progression</p>
        </div>

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

            <!-- Character Grid -->
            <div v-if="filteredUserCharacters.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <router-link
                    v-for="char in filteredUserCharacters"
                    :key="char.name + '-' + char.realmSlug"
                    :to="`/character/${char.realmSlug}/${char.name.toLowerCase()}`"
                    class="bg-slate-800/40 border border-white/5 p-5 rounded-2xl hover:bg-slate-800/60 hover:border-blue-500/20 transition-all group text-left"
                >
                    <div class="flex items-center gap-4">
                        <img
                            v-if="char.avatarUrl"
                            :src="char.avatarUrl"
                            :alt="char.name"
                            class="w-12 h-12 rounded-xl border border-white/10 shadow-lg bg-slate-800 object-cover"
                            :style="{ borderColor: (classColors[char.classId] || '#FFFFFF') + '30' }"
                        >
                        <div
                            v-else
                            class="w-12 h-12 rounded-xl flex items-center justify-center text-lg font-black border border-white/10 shadow-lg"
                            :style="{ backgroundColor: (classColors[char.classId] || '#FFFFFF') + '15', color: classColors[char.classId] || '#FFFFFF', borderColor: (classColors[char.classId] || '#FFFFFF') + '30' }"
                        >
                            {{ char.name.charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-base md:text-lg font-bold truncate group-hover:text-blue-400 transition-colors" :style="{ color: classColors[char.classId] || '#FFFFFF' }">
                                {{ char.name }}
                            </div>
                            <div class="text-xs sm:text-sm text-slate-500 truncate">{{ char.realm }}</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-3 text-[11px] sm:text-xs text-slate-400">
                        <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5 font-mono">Niv {{ char.level }}</span>
                        <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5 font-bold" :style="{ color: char.faction === 'Alliance' ? '#3b82f6' : '#ef4444' }">{{ char.faction }}</span>
                        <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5">{{ char.raceName }}</span>
                        <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5" :style="{ color: classColors[char.classId] || '#FFFFFF' }">{{ char.className }}</span>
                    </div>
                </router-link>
            </div>

            <!-- No search results -->
            <div v-else class="text-center py-16">
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

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useCharacterStore } from '../stores/character';
import { classColors } from '../utils/classColors';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const store = useCharacterStore();
const characterSearch = ref('');
const sortBy = ref('name');

const sortOptions = [
    { key: 'name', label: 'Nom' },
    { key: 'level', label: 'Niveau' },
    { key: 'class', label: 'Classe' },
    { key: 'realm', label: 'Royaume' },
];

onMounted(() => {
    if (!store.userCharacters.length) {
        store.fetchUserCharacters();
    }
});

const filteredUserCharacters = computed(() => {
    const q = characterSearch.value.toLowerCase().trim();
    let list = store.userCharacters;
    if (q) {
        list = list.filter(c =>
            c.name.toLowerCase().includes(q) ||
            c.realm.toLowerCase().includes(q) ||
            c.className.toLowerCase().includes(q) ||
            c.raceName.toLowerCase().includes(q) ||
            c.faction.toLowerCase().includes(q)
        );
    }
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
