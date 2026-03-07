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
                        class="px-3 md:px-4 py-1.5 rounded-md text-xs md:text-sm font-semibold bg-slate-800/80 border border-white/5 text-slate-300 hover:text-white hover:bg-slate-700 transition-all whitespace-nowrap"
                    >
                        Se connecter
                    </a>

                    <!-- Desktop search inline (lg+) -->
                    <div class="hidden lg:flex items-center gap-3">
                        <div class="w-px h-6 bg-white/10"></div>
                        <input
                            v-model="search.realm"
                            type="text"
                            placeholder="Royaume"
                            class="bg-slate-800/80 border-none rounded-md px-3 py-1.5 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500 w-32"
                        >
                        <input
                            v-model="search.name"
                            type="text"
                            placeholder="Nom du personnage"
                            @keyup.enter="searchCharacter"
                            class="bg-slate-800/80 border-none rounded-md px-3 py-1.5 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500 w-44"
                        >
                        <button
                            @click="searchCharacter"
                            :disabled="store.loading"
                            class="btn-gradient text-white font-semibold px-4 py-1.5 rounded-md text-sm shadow-lg shadow-blue-500/10"
                        >
                            {{ store.loading ? 'Recherche...' : 'Rechercher' }}
                        </button>
                    </div>
                </div>

                <!-- Mobile hamburger (< sm) -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="sm:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                    <svg v-if="!mobileMenuOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Mobile menu (< sm) -->
            <div v-if="mobileMenuOpen" class="sm:hidden pb-3 space-y-3 border-t border-white/5 pt-3">
                <div v-if="store.isAuthenticated" class="flex flex-col gap-2">
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
                </div>
                <a
                    v-else
                    href="/auth/blizzard/redirect"
                    class="block w-full px-3 py-2 rounded-md text-sm font-semibold bg-slate-800/80 border border-white/5 text-slate-300 hover:text-white hover:bg-slate-700 transition-all text-center"
                >
                    Se connecter
                </a>
                <div class="space-y-2">
                    <div class="flex gap-2">
                        <input
                            v-model="search.realm"
                            type="text"
                            placeholder="Royaume"
                            class="flex-1 min-w-0 bg-slate-800/80 border-none rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                        >
                        <input
                            v-model="search.name"
                            type="text"
                            placeholder="Personnage"
                            @keyup.enter="searchCharacter(); mobileMenuOpen = false"
                            class="flex-1 min-w-0 bg-slate-800/80 border-none rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                        >
                    </div>
                    <button
                        @click="searchCharacter(); mobileMenuOpen = false"
                        :disabled="store.loading"
                        class="w-full btn-gradient text-white font-semibold px-4 py-2 rounded-md text-sm shadow-lg shadow-blue-500/10"
                    >
                        {{ store.loading ? 'Recherche...' : 'Rechercher' }}
                    </button>
                </div>
            </div>

            <!-- Tablet search row (sm to lg) -->
            <div class="hidden sm:flex lg:hidden pb-3 gap-2">
                <input
                    v-model="search.realm"
                    type="text"
                    placeholder="Royaume"
                    class="flex-1 min-w-0 bg-slate-800/80 border-none rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                >
                <input
                    v-model="search.name"
                    type="text"
                    placeholder="Nom du personnage"
                    @keyup.enter="searchCharacter"
                    class="flex-1 min-w-0 bg-slate-800/80 border-none rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                >
                <button
                    @click="searchCharacter"
                    :disabled="store.loading"
                    class="btn-gradient text-white font-semibold px-4 py-2 rounded-md text-sm shadow-lg shadow-blue-500/10 shrink-0"
                >
                    {{ store.loading ? '...' : 'Rechercher' }}
                </button>
            </div>
        </div>
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
