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

                    <!-- Discord -->
                    <a
                        href="https://discord.gg/wa49gGF8cr"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Rejoindre le Discord WowPlanet"
                        class="flex items-center gap-1.5 px-3 md:px-4 py-1.5 rounded-md text-xs md:text-sm font-semibold text-white transition-opacity hover:opacity-90 whitespace-nowrap bg-[#5865F2]"
                    >
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.033.056a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>
                        </svg>
                        Discord
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

                </div>

                <!-- Mobile right controls (< sm) -->
                <div class="flex sm:hidden items-center gap-2">
                    <a
                        href="https://discord.gg/wa49gGF8cr"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Rejoindre le Discord WowPlanet"
                        class="min-w-11 min-h-11 flex items-center justify-center rounded-lg text-white transition-opacity hover:opacity-90 bg-[#5865F2]"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.033.056a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>
                        </svg>
                    </a>
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        :aria-label="mobileMenuOpen ? 'Fermer le menu' : 'Ouvrir le menu'"
                        :aria-expanded="mobileMenuOpen"
                        class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                    >
                        <svg v-if="!mobileMenuOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
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
            </div>
            </Transition>

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

const mobileMenuOpen = ref(false);

const handleLogout = async () => {
    await store.logout();
    router.push('/');
};

watch(route, () => {
    mobileMenuOpen.value = false;
});
</script>
