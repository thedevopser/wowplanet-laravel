<template>
    <div class="space-y-8 py-6 sm:py-8">
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
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" :content="meta.ogTitle">
            <meta name="twitter:description" :content="meta.ogDescription">
            <meta name="twitter:image" :content="meta.ogImage">
        </Head>

        <div class="text-center max-w-3xl mx-auto">
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-black mb-4">
                <span class="bg-clip-text text-transparent bg-linear-to-r from-blue-200 via-blue-400 to-blue-600">Base de données WoW</span>
            </h1>
            <p class="text-base sm:text-lg text-slate-400 leading-relaxed">
                Explorez la base de données complète de World of Warcraft entièrement en français.
                Montures, hauts-faits, quêtes, mascottes, décorations et professions.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 max-w-5xl mx-auto">
            <Link
                href="/base-de-donnees/montures"
                class="bg-slate-800/40 border border-white/5 rounded-2xl p-6 sm:p-8 text-center group hover:border-amber-500/30 hover:bg-slate-800/60 transition-all"
            >
                <div class="w-14 h-14 mx-auto bg-amber-600/10 border border-amber-500/20 rounded-xl flex items-center justify-center text-amber-400 mb-4 group-hover:scale-110 transition-transform p-3"><CategoryIcon category="mounts" /></div>
                <h2 class="text-lg sm:text-xl font-bold text-white mb-2 group-hover:text-amber-400 transition-colors">Montures</h2>
                <p class="text-sm text-slate-500 mb-3">Triées par catégorie et source d'obtention</p>
                <div class="text-2xl font-black text-amber-400 font-mono">{{ counts.mounts?.toLocaleString('fr-FR') || '...' }}</div>
            </Link>

            <Link
                href="/base-de-donnees/hauts-faits"
                class="bg-slate-800/40 border border-white/5 rounded-2xl p-6 sm:p-8 text-center group hover:border-amber-500/30 hover:bg-slate-800/60 transition-all"
            >
                <div class="w-14 h-14 mx-auto bg-amber-600/10 border border-amber-500/20 rounded-xl flex items-center justify-center text-amber-400 mb-4 group-hover:scale-110 transition-transform p-3"><CategoryIcon category="achievements" /></div>
                <h2 class="text-lg sm:text-xl font-bold text-white mb-2 group-hover:text-amber-400 transition-colors">Hauts-faits</h2>
                <p class="text-sm text-slate-500 mb-3">Classés par extension et catégorie</p>
                <div class="text-2xl font-black text-amber-400 font-mono">{{ counts.achievements?.toLocaleString('fr-FR') || '...' }}</div>
            </Link>

            <Link
                href="/base-de-donnees/quetes"
                class="bg-slate-800/40 border border-white/5 rounded-2xl p-6 sm:p-8 text-center group hover:border-blue-500/30 hover:bg-slate-800/60 transition-all"
            >
                <div class="w-14 h-14 mx-auto bg-blue-600/10 border border-blue-500/20 rounded-xl flex items-center justify-center text-blue-400 mb-4 group-hover:scale-110 transition-transform p-3"><CategoryIcon category="quests" /></div>
                <h2 class="text-lg sm:text-xl font-bold text-white mb-2 group-hover:text-blue-400 transition-colors">Quêtes</h2>
                <p class="text-sm text-slate-500 mb-3">Triées par extension et zone</p>
                <div class="text-2xl font-black text-blue-400 font-mono">{{ counts.quests?.toLocaleString('fr-FR') || '...' }}</div>
            </Link>

            <Link
                href="/base-de-donnees/mascottes"
                class="bg-slate-800/40 border border-white/5 rounded-2xl p-6 sm:p-8 text-center group hover:border-blue-500/30 hover:bg-slate-800/60 transition-all"
            >
                <div class="w-14 h-14 mx-auto bg-blue-600/10 border border-blue-500/20 rounded-xl flex items-center justify-center text-blue-400 mb-4 group-hover:scale-110 transition-transform p-3"><CategoryIcon category="pets" /></div>
                <h2 class="text-lg sm:text-xl font-bold text-white mb-2 group-hover:text-blue-400 transition-colors">Mascottes</h2>
                <p class="text-sm text-slate-500 mb-3">Triées par catégorie et source</p>
                <div class="text-2xl font-black text-blue-400 font-mono">{{ counts.pets?.toLocaleString('fr-FR') || '...' }}</div>
            </Link>

            <Link
                href="/base-de-donnees/decorations"
                class="bg-slate-800/40 border border-white/5 rounded-2xl p-6 sm:p-8 text-center group hover:border-violet-500/30 hover:bg-slate-800/60 transition-all"
            >
                <div class="w-14 h-14 mx-auto bg-violet-600/10 border border-violet-500/20 rounded-xl flex items-center justify-center text-violet-400 mb-4 group-hover:scale-110 transition-transform p-3"><CategoryIcon category="decor" /></div>
                <h2 class="text-lg sm:text-xl font-bold text-white mb-2 group-hover:text-violet-400 transition-colors">Décorations</h2>
                <p class="text-sm text-slate-500 mb-3">Triées par catégorie et source</p>
                <div class="text-2xl font-black text-violet-400 font-mono">{{ counts.decors?.toLocaleString('fr-FR') || '...' }}</div>
            </Link>

            <Link
                href="/base-de-donnees/professions"
                class="bg-slate-800/40 border border-white/5 rounded-2xl p-6 sm:p-8 text-center group hover:border-emerald-500/30 hover:bg-slate-800/60 transition-all"
            >
                <div class="w-14 h-14 mx-auto bg-emerald-600/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 mb-4 group-hover:scale-110 transition-transform p-3"><CategoryIcon category="professions" /></div>
                <h2 class="text-lg sm:text-xl font-bold text-white mb-2 group-hover:text-emerald-400 transition-colors">Professions</h2>
                <p class="text-sm text-slate-500 mb-3">Recettes classées par extension</p>
                <div class="text-2xl font-black text-emerald-400 font-mono">{{ counts.recipes?.toLocaleString('fr-FR') || '...' }}</div>
                <div class="text-[10px] text-slate-600 font-mono mt-1">{{ counts.professions }} professions</div>
            </Link>
        </div>

        <div class="text-center text-xs sm:text-sm text-slate-600 max-w-lg mx-auto">
            Données de référence du jeu entièrement en français.
            <br>Connectez-vous avec Battle.net pour suivre votre progression personnelle.
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
import { Head, Link } from '@inertiajs/vue3';
import CategoryIcon from '../components/CategoryIcon.vue';

defineProps({
    meta: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
});
</script>
