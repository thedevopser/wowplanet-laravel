<template>
    <div class="space-y-12 sm:space-y-16 py-6 sm:py-8">
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
    </div>
</template>

<script setup>
import { useCharacterStore } from '../stores/character';
import CategoryIcon from '../components/CategoryIcon.vue';

const store = useCharacterStore();
</script>
