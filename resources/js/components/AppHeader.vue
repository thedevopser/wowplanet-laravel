<template>
    <header class="border-b border-white/10 bg-slate-900/50 backdrop-blur-md z-50 shrink-0">
        <div class="max-w-7xl mx-auto px-3 sm:px-4">
            <!-- Main header row -->
            <div class="h-12 sm:h-14 md:h-16 flex items-center justify-between gap-2 sm:gap-3">
                <router-link to="/" class="flex items-center gap-2 cursor-pointer hover:opacity-80 transition-opacity shrink-0">
                    <img src="/images/logo.png" alt="WowPlanet Logo" class="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 rounded-xl shadow-lg shadow-blue-500/30 object-cover">
                    <h1 class="text-lg sm:text-xl md:text-2xl font-black bg-clip-text text-transparent bg-linear-to-r from-blue-200 to-blue-500">WowPlanet</h1>
                </router-link>

                <!-- Desktop/tablet nav (sm+) -->
                <div class="hidden sm:flex items-center gap-2 md:gap-3">
                    <router-link
                        to="/base-de-donnees"
                        :class="[
                            'px-3 md:px-4 py-1.5 rounded-md text-xs md:text-sm font-semibold transition-all border whitespace-nowrap',
                            route.path.startsWith('/base-de-donnees')
                                ? 'bg-blue-600 border-blue-400 text-white shadow-lg shadow-blue-500/20'
                                : 'bg-slate-800/80 border-white/5 text-slate-300 hover:text-white hover:bg-slate-700'
                        ]"
                    >
                        Base de données
                    </router-link>
                    <template v-if="store.isAuthenticated">
                        <router-link
                            to="/my-characters"
                            :class="[
                                'px-3 md:px-4 py-1.5 rounded-md text-xs md:text-sm font-semibold transition-all border whitespace-nowrap',
                                route.name === 'my-characters'
                                    ? 'bg-blue-600 border-blue-400 text-white shadow-lg shadow-blue-500/20'
                                    : 'bg-slate-800/80 border-white/5 text-slate-300 hover:text-white hover:bg-slate-700'
                            ]"
                        >
                            Mes personnages
                        </router-link>
                        <router-link
                            to="/class-stats"
                            :class="[
                                'px-3 md:px-4 py-1.5 rounded-md text-xs md:text-sm font-semibold transition-all border whitespace-nowrap',
                                route.name === 'class-stats'
                                    ? 'bg-blue-600 border-blue-400 text-white shadow-lg shadow-blue-500/20'
                                    : 'bg-slate-800/80 border-white/5 text-slate-300 hover:text-white hover:bg-slate-700'
                            ]"
                        >
                            Mes classes
                        </router-link>
                        <router-link
                            to="/my-score"
                            :class="[
                                'px-3 md:px-4 py-1.5 rounded-md text-xs md:text-sm font-semibold transition-all border whitespace-nowrap',
                                route.name === 'my-score'
                                    ? 'bg-blue-600 border-blue-400 text-white shadow-lg shadow-blue-500/20'
                                    : 'bg-slate-800/80 border-white/5 text-slate-300 hover:text-white hover:bg-slate-700'
                            ]"
                        >
                            Mon score
                        </router-link>
                        <router-link
                            v-if="store.isAdmin"
                            to="/admin"
                            :class="[
                                'px-3 md:px-4 py-1.5 rounded-md text-xs md:text-sm font-semibold transition-all border whitespace-nowrap',
                                route.name === 'admin'
                                    ? 'bg-red-600 border-red-400 text-white shadow-lg shadow-red-500/20'
                                    : 'bg-slate-800/80 border-white/5 text-red-400 hover:text-white hover:bg-slate-700'
                            ]"
                        >
                            Admin
                        </router-link>
                        <button
                            @click="handleLogout"
                            class="px-2 md:px-3 py-1.5 rounded-md text-xs text-slate-500 hover:text-slate-300 transition-colors whitespace-nowrap"
                        >
                            Déconnexion
                        </button>
                    </template>
                    <a
                        v-else
                        href="/auth/blizzard/redirect"
                        class="btn-gradient px-3 md:px-4 py-1.5 rounded-md text-xs md:text-sm font-semibold text-white shadow-lg shadow-blue-500/10 whitespace-nowrap"
                    >
                        Se connecter
                    </a>

                    <!-- Theme toggle -->
                    <button
                        @click="store.toggleTheme()"
                        :aria-label="store.theme === 'dark' ? 'Passer en mode clair' : 'Passer en mode sombre'"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800/50 transition-colors"
                    >
                        <svg v-if="store.theme === 'dark'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </button>

                    <!-- Desktop search inline (lg+) -->
                    <div class="hidden lg:flex items-center gap-3">
                        <div class="w-px h-6 bg-white/10"></div>
                        <input
                            v-model="search.realm"
                            type="text"
                            placeholder="Royaume"
                            aria-label="Royaume du serveur"
                            list="realm-suggestions"
                            class="bg-slate-800/80 border-none rounded-md px-3 py-1.5 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500 w-32"
                        >
                        <input
                            v-model="search.name"
                            type="text"
                            placeholder="Nom du personnage"
                            aria-label="Nom du personnage"
                            @keyup.enter="searchCharacter"
                            class="bg-slate-800/80 border-none rounded-md px-3 py-1.5 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500 w-44"
                        >
                        <button
                            @click="searchCharacter"
                            :disabled="store.loading"
                            aria-label="Lancer la recherche"
                            class="btn-gradient text-white font-semibold px-4 py-1.5 rounded-md text-sm shadow-lg shadow-blue-500/10"
                        >
                            {{ store.loading ? 'Recherche...' : 'Rechercher' }}
                        </button>
                    </div>
                </div>

                <!-- Mobile hamburger (< sm) -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" :aria-label="mobileMenuOpen ? 'Fermer le menu' : 'Ouvrir le menu'" :aria-expanded="mobileMenuOpen" class="sm:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                    <svg v-if="!mobileMenuOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Mobile menu (< sm) -->
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                leave-active-class="transition-all duration-150 ease-in"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
            <div v-if="mobileMenuOpen" class="sm:hidden pb-3 space-y-3 border-t border-white/5 pt-3">
                <div class="flex flex-col gap-2">
                    <router-link
                        to="/base-de-donnees"
                        @click="mobileMenuOpen = false"
                        :class="[
                            'w-full px-3 py-2 rounded-md text-sm font-semibold transition-all border text-left',
                            route.path.startsWith('/base-de-donnees')
                                ? 'bg-blue-600 border-blue-400 text-white'
                                : 'bg-slate-800/80 border-white/5 text-slate-300'
                        ]"
                    >
                        Base de données
                    </router-link>
                    <template v-if="store.isAuthenticated">
                        <router-link
                            to="/my-characters"
                            @click="mobileMenuOpen = false"
                            :class="[
                                'w-full px-3 py-2 rounded-md text-sm font-semibold transition-all border text-left',
                                route.name === 'my-characters'
                                    ? 'bg-blue-600 border-blue-400 text-white'
                                    : 'bg-slate-800/80 border-white/5 text-slate-300'
                            ]"
                        >
                            Mes personnages
                        </router-link>
                        <router-link
                            to="/class-stats"
                            @click="mobileMenuOpen = false"
                            :class="[
                                'w-full px-3 py-2 rounded-md text-sm font-semibold transition-all border text-left',
                                route.name === 'class-stats'
                                    ? 'bg-blue-600 border-blue-400 text-white'
                                    : 'bg-slate-800/80 border-white/5 text-slate-300'
                            ]"
                        >
                            Mes classes
                        </router-link>
                        <router-link
                            to="/my-score"
                            @click="mobileMenuOpen = false"
                            :class="[
                                'w-full px-3 py-2 rounded-md text-sm font-semibold transition-all border text-left',
                                route.name === 'my-score'
                                    ? 'bg-blue-600 border-blue-400 text-white'
                                    : 'bg-slate-800/80 border-white/5 text-slate-300'
                            ]"
                        >
                            Mon score
                        </router-link>
                        <router-link
                            v-if="store.isAdmin"
                            to="/admin"
                            @click="mobileMenuOpen = false"
                            :class="[
                                'w-full px-3 py-2 rounded-md text-sm font-semibold transition-all border text-left',
                                route.name === 'admin'
                                    ? 'bg-red-600 border-red-400 text-white'
                                    : 'bg-slate-800/80 border-white/5 text-red-400'
                            ]"
                        >
                            Admin
                        </router-link>
                        <button
                            @click="handleLogout(); mobileMenuOpen = false"
                            class="w-full px-3 py-2 rounded-md text-sm text-slate-500 hover:text-slate-300 transition-colors text-left"
                        >
                            Déconnexion
                        </button>
                    </template>
                    <a
                        v-else
                        href="/auth/blizzard/redirect"
                        class="block w-full btn-gradient px-3 py-2 rounded-md text-sm font-semibold text-white text-center shadow-lg shadow-blue-500/10"
                    >
                        Se connecter
                    </a>
                    <!-- Mobile theme toggle -->
                    <button
                        @click="store.toggleTheme()"
                        class="w-full px-3 py-2 rounded-md text-sm text-slate-400 hover:text-slate-200 transition-colors text-left flex items-center gap-2"
                    >
                        <svg v-if="store.theme === 'dark'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        {{ store.theme === 'dark' ? 'Mode clair' : 'Mode sombre' }}
                    </button>
                </div>
                <div class="space-y-2">
                    <div class="flex gap-2">
                        <input
                            v-model="search.realm"
                            type="text"
                            placeholder="Royaume"
                            aria-label="Royaume du serveur"
                            list="realm-suggestions"
                            class="flex-1 min-w-0 bg-slate-800/80 border-none rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                        >
                        <input
                            v-model="search.name"
                            type="text"
                            placeholder="Personnage"
                            aria-label="Nom du personnage"
                            @keyup.enter="searchCharacter(); mobileMenuOpen = false"
                            class="flex-1 min-w-0 bg-slate-800/80 border-none rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                        >
                    </div>
                    <button
                        @click="searchCharacter(); mobileMenuOpen = false"
                        :disabled="store.loading"
                        aria-label="Lancer la recherche"
                        class="w-full btn-gradient text-white font-semibold px-4 py-2 rounded-md text-sm shadow-lg shadow-blue-500/10"
                    >
                        {{ store.loading ? 'Recherche...' : 'Rechercher' }}
                    </button>
                </div>
            </div>
            </Transition>

            <!-- Tablet search row (sm to lg) -->
            <div class="hidden sm:flex lg:hidden pb-3 gap-2">
                <input
                    v-model="search.realm"
                    type="text"
                    placeholder="Royaume"
                    aria-label="Royaume du serveur"
                    class="flex-1 min-w-0 bg-slate-800/80 border-none rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                >
                <input
                    v-model="search.name"
                    type="text"
                    placeholder="Nom du personnage"
                    aria-label="Nom du personnage"
                    @keyup.enter="searchCharacter"
                    class="flex-1 min-w-0 bg-slate-800/80 border-none rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                >
                <button
                    @click="searchCharacter"
                    :disabled="store.loading"
                    aria-label="Lancer la recherche"
                    class="btn-gradient text-white font-semibold px-4 py-2 rounded-md text-sm shadow-lg shadow-blue-500/10 shrink-0"
                >
                    {{ store.loading ? '...' : 'Rechercher' }}
                </button>
            </div>
        </div>
        <datalist id="realm-suggestions">
            <option value="Dalaran" />
            <option value="Hyjal" />
            <option value="Archimonde" />
            <option value="Ysondre" />
            <option value="Kael'thas" />
            <option value="Elune" />
            <option value="Sargeras" />
            <option value="Cho'gall" />
            <option value="Illidan" />
            <option value="Khaz Modan" />
            <option value="Ner'zhul" />
            <option value="Connected Dalaran" />
        </datalist>
    </header>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useCharacterStore } from '../stores/character';

const router = useRouter();
const route = useRoute();
const store = useCharacterStore();

const search = ref({ realm: 'Dalaran', name: '' });
const mobileMenuOpen = ref(false);

const searchCharacter = () => {
    if (search.value.name && search.value.realm) {
        router.push({ name: 'character', params: { realm: search.value.realm.toLowerCase(), name: search.value.name.toLowerCase() } });
    }
};

const handleLogout = async () => {
    await store.logout();
    router.push('/');
};

watch(route, () => {
    mobileMenuOpen.value = false;
});
</script>
