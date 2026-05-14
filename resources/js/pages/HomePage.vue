<template>
    <div
        data-testid="homepage-root"
        :class="['space-y-12 sm:space-y-16 py-6 sm:py-8', { 'pb-14': bannerVisible }]"
    >
        <!-- Hero -->
        <div class="text-center max-w-3xl mx-auto">
            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto bg-slate-800 rounded-2xl sm:rounded-3xl border border-white/10 flex items-center justify-center mb-6 sm:mb-8 shadow-2xl shadow-blue-500/10">
                <img src="/images/logo.png" alt="WowPlanet" class="w-10 h-10 sm:w-14 sm:h-14 rounded-lg sm:rounded-xl object-cover">
            </div>
            <h2 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black mb-4">
                <span class="bg-clip-text text-transparent bg-linear-to-r from-blue-200 via-blue-400 to-blue-600">Suivez votre progression</span>
            </h2>
            <p class="text-base sm:text-lg md:text-xl text-slate-400 leading-relaxed max-w-2xl mx-auto">
                WowPlanet analyse votre personnage World of Warcraft via l'API Blizzard et compare vos accomplissements avec la base de donn&eacute;es compl&egrave;te du jeu. Quêtes, hauts-faits, montures, mascottes : visualisez tout ce qu'il vous reste &agrave; accomplir.
            </p>
        </div>

        <!-- Battle.net CTA -->
        <div class="max-w-xl mx-auto text-center">
            <div class="card-glass rounded-2xl border p-6 sm:p-8">
                <template v-if="store.isAuthenticated">
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-white mb-2">Bienvenue !</h3>
                    <p class="text-slate-400 text-sm sm:text-base mb-6">Vous &ecirc;tes connect&eacute; avec Battle.net. Acc&eacute;dez directement &agrave; tous vos personnages.</p>
                    <router-link
                        to="/my-characters"
                        class="btn-gradient text-white font-semibold px-6 py-2.5 rounded-lg text-sm shadow-lg shadow-blue-500/20 inline-block"
                    >
                        Voir mes personnages
                    </router-link>
                </template>
                <template v-else>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-white mb-2">Commencer</h3>
                    <p class="text-slate-400 text-sm sm:text-base mb-6">Connectez-vous avec Battle.net pour acc&eacute;der &agrave; tous vos personnages, ou recherchez un personnage manuellement.</p>
                    <div class="flex flex-col items-center gap-4">
                        <a
                            href="/auth/blizzard/redirect"
                            class="btn-gradient text-white font-semibold px-6 py-2.5 rounded-lg text-sm shadow-lg shadow-blue-500/20 inline-block"
                        >
                            Se connecter avec Battle.net
                        </a>
                        <div class="flex items-center gap-3 text-slate-600 text-xs">
                            <div class="h-px w-12 bg-slate-800"></div>
                            ou
                            <div class="h-px w-12 bg-slate-800"></div>
                        </div>
                        <div class="flex flex-wrap items-center justify-center gap-2 text-slate-500 text-sm">
                            <span class="px-2 sm:px-3 py-1 bg-slate-800 rounded-lg border border-white/5 font-mono text-xs">Royaume</span>
                            <span>+</span>
                            <span class="px-2 sm:px-3 py-1 bg-slate-800 rounded-lg border border-white/5 font-mono text-xs">Personnage</span>
                            <span>&#8594;</span>
                            <span class="px-2 sm:px-3 py-1 bg-blue-600/20 text-blue-400 rounded-lg border border-blue-500/20 font-mono text-xs">Rechercher</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Discord invite card -->
        <div class="max-w-xl mx-auto">
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-indigo-500/20 bg-indigo-600/10 px-5 py-4">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600/20 border border-indigo-500/20">
                        <svg class="h-5 w-5 text-[#5865F2]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.033.056a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-white">Rejoins la communauté</p>
                        <p class="text-xs text-slate-400">Propose des features · Signale un bug · Échange avec les joueurs</p>
                    </div>
                </div>
                <a
                    href="https://discord.gg/wa49gGF8cr"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="shrink-0 rounded-md px-4 py-2 text-xs font-bold text-white transition-opacity hover:opacity-90 whitespace-nowrap bg-[#5865F2]"
                >
                    Rejoindre →
                </a>
            </div>
        </div>

        <!-- Features -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 max-w-5xl mx-auto">
            <div class="bg-slate-800/40 border border-white/5 rounded-2xl p-4 sm:p-6 text-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 mx-auto bg-blue-600/10 border border-blue-500/20 rounded-xl flex items-center justify-center text-blue-400 mb-3 sm:mb-4 p-2 sm:p-2.5"><CategoryIcon category="quests" /></div>
                <h4 class="font-bold text-white text-sm sm:text-base mb-1">Qu&ecirc;tes</h4>
                <p class="text-[10px] sm:text-xs md:text-sm text-slate-500">Progression par zone et par extension, avec plus de 21 000 qu&ecirc;tes r&eacute;f&eacute;renc&eacute;es.</p>
            </div>
            <div class="bg-slate-800/40 border border-white/5 rounded-2xl p-4 sm:p-6 text-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 mx-auto bg-amber-600/10 border border-amber-500/20 rounded-xl flex items-center justify-center text-amber-400 mb-3 sm:mb-4 p-2 sm:p-2.5"><CategoryIcon category="achievements" /></div>
                <h4 class="font-bold text-white text-sm sm:text-base mb-1">Hauts-faits</h4>
                <p class="text-[10px] sm:text-xs md:text-sm text-slate-500">Plus de 8 600 hauts-faits tri&eacute;s par cat&eacute;gorie et par extension.</p>
            </div>
            <div class="bg-slate-800/40 border border-white/5 rounded-2xl p-4 sm:p-6 text-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 mx-auto bg-amber-600/10 border border-amber-500/20 rounded-xl flex items-center justify-center text-amber-400 mb-3 sm:mb-4 p-2 sm:p-2.5"><CategoryIcon category="mounts" /></div>
                <h4 class="font-bold text-white text-sm sm:text-base mb-1">Montures</h4>
                <p class="text-[10px] sm:text-xs md:text-sm text-slate-500">1 569 montures avec statut d'obtention et lien Wowhead.</p>
            </div>
            <div class="bg-slate-800/40 border border-white/5 rounded-2xl p-4 sm:p-6 text-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 mx-auto bg-blue-600/10 border border-blue-500/20 rounded-xl flex items-center justify-center text-blue-400 mb-3 sm:mb-4 p-2 sm:p-2.5"><CategoryIcon category="pets" /></div>
                <h4 class="font-bold text-white text-sm sm:text-base mb-1">Mascottes</h4>
                <p class="text-[10px] sm:text-xs md:text-sm text-slate-500">2 117 mascottes de combat avec suivi de collection.</p>
            </div>
        </div>

        <!-- Database browse section -->
        <div class="max-w-4xl mx-auto">
            <h3 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 mb-4 sm:mb-6">
                Explorez la base de donn&eacute;es
                <div class="flex-1 h-px bg-slate-700"></div>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                <router-link to="/base-de-donnees/montures" class="flex items-center gap-4 p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-amber-500/30 transition-all">
                    <div class="w-10 h-10 bg-amber-600/10 border border-amber-500/20 rounded-lg flex items-center justify-center text-amber-400 shrink-0 group-hover:scale-110 transition-transform p-2"><CategoryIcon category="mounts" /></div>
                    <div>
                        <div class="text-sm font-bold text-slate-200 group-hover:text-amber-400 transition-colors">Montures</div>
                        <div class="text-xs text-slate-500">Par cat&eacute;gorie et source</div>
                    </div>
                </router-link>
                <router-link to="/base-de-donnees/hauts-faits" class="flex items-center gap-4 p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-amber-500/30 transition-all">
                    <div class="w-10 h-10 bg-amber-600/10 border border-amber-500/20 rounded-lg flex items-center justify-center text-amber-400 shrink-0 group-hover:scale-110 transition-transform p-2"><CategoryIcon category="achievements" /></div>
                    <div>
                        <div class="text-sm font-bold text-slate-200 group-hover:text-amber-400 transition-colors">Hauts-faits</div>
                        <div class="text-xs text-slate-500">Par extension et cat&eacute;gorie</div>
                    </div>
                </router-link>
                <router-link to="/base-de-donnees/quetes" class="flex items-center gap-4 p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-blue-500/30 transition-all">
                    <div class="w-10 h-10 bg-blue-600/10 border border-blue-500/20 rounded-lg flex items-center justify-center text-blue-400 shrink-0 group-hover:scale-110 transition-transform p-2"><CategoryIcon category="quests" /></div>
                    <div>
                        <div class="text-sm font-bold text-slate-200 group-hover:text-blue-400 transition-colors">Qu&ecirc;tes</div>
                        <div class="text-xs text-slate-500">Par extension et zone</div>
                    </div>
                </router-link>
                <router-link to="/base-de-donnees/mascottes" class="flex items-center gap-4 p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-blue-500/30 transition-all">
                    <div class="w-10 h-10 bg-blue-600/10 border border-blue-500/20 rounded-lg flex items-center justify-center text-blue-400 shrink-0 group-hover:scale-110 transition-transform p-2"><CategoryIcon category="pets" /></div>
                    <div>
                        <div class="text-sm font-bold text-slate-200 group-hover:text-blue-400 transition-colors">Mascottes</div>
                        <div class="text-xs text-slate-500">Par cat&eacute;gorie et source</div>
                    </div>
                </router-link>
                <router-link to="/base-de-donnees/decorations" class="flex items-center gap-4 p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-violet-500/30 transition-all">
                    <div class="w-10 h-10 bg-violet-600/10 border border-violet-500/20 rounded-lg flex items-center justify-center text-violet-400 shrink-0 group-hover:scale-110 transition-transform p-2"><CategoryIcon category="decor" /></div>
                    <div>
                        <div class="text-sm font-bold text-slate-200 group-hover:text-violet-400 transition-colors">D&eacute;corations</div>
                        <div class="text-xs text-slate-500">Par cat&eacute;gorie et source</div>
                    </div>
                </router-link>
                <router-link to="/base-de-donnees/professions" class="flex items-center gap-4 p-4 rounded-xl bg-slate-800/40 border border-white/5 group hover:border-emerald-500/30 transition-all">
                    <div class="w-10 h-10 bg-emerald-600/10 border border-emerald-500/20 rounded-lg flex items-center justify-center text-emerald-400 shrink-0 group-hover:scale-110 transition-transform p-2"><CategoryIcon category="professions" /></div>
                    <div>
                        <div class="text-sm font-bold text-slate-200 group-hover:text-emerald-400 transition-colors">Professions</div>
                        <div class="text-xs text-slate-500">Recettes par extension</div>
                    </div>
                </router-link>
            </div>
        </div>

        <!-- Data source info -->
        <div class="text-center text-xs sm:text-sm text-slate-600 max-w-lg mx-auto">
            Donn&eacute;es synchronis&eacute;es depuis l'API officielle Blizzard. Tous les noms sont en fran&ccedil;ais.
            <br>Chaque &eacute;l&eacute;ment est li&eacute; &agrave; sa fiche Wowhead pour plus de d&eacute;tails.
        </div>

        <DiscordBanner @dismissed="bannerVisible = false" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useCharacterStore } from '../stores/character';
import CategoryIcon from '../components/CategoryIcon.vue';
import DiscordBanner from '../components/DiscordBanner.vue';

const store = useCharacterStore();
const bannerVisible = ref(!localStorage.getItem('discord_banner_dismissed'));
</script>
