<template>
    <div class="max-w-4xl mx-auto py-6 sm:py-10 space-y-8">
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

        <div class="space-y-3">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Nos Addons</h1>
            <p class="text-sm text-slate-300 leading-relaxed">
                Au-del&agrave; du site, nous d&eacute;veloppons des addons gratuits et open source pour
                <strong class="text-white">World of Warcraft</strong>. Chacun est disponible sur CurseForge
                et maintenu &agrave; jour pour la version actuelle du jeu.
            </p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <article
                v-for="addon in addons"
                :key="addon.slug"
                class="flex flex-col bg-slate-800/50 border border-white/5 rounded-lg p-5 space-y-4"
            >
                <div class="flex items-center gap-4">
                    <img
                        :src="addon.image"
                        :alt="'Icône ' + addon.name"
                        width="64"
                        height="64"
                        class="w-16 h-16 rounded-lg shrink-0"
                        loading="lazy"
                    >
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-white">{{ addon.name }}</h2>
                        <p class="text-xs text-slate-500">{{ addon.tagline }}</p>
                    </div>
                </div>

                <p class="text-sm text-slate-300 leading-relaxed" v-html="addon.description"></p>

                <ul class="list-disc list-inside text-sm text-slate-300 space-y-1 ml-1">
                    <li v-for="(feature, index) in addon.features" :key="index" v-html="feature"></li>
                </ul>

                <a
                    :href="addon.curseforge"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-auto inline-flex items-center justify-center gap-2 rounded-lg bg-blue-500 hover:bg-blue-400 px-4 py-2 text-sm font-semibold text-white transition-colors"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    Voir sur CurseForge
                </a>
            </article>
        </div>

        <div class="bg-amber-500/10 border border-amber-500/20 rounded-lg p-4 text-amber-200 text-sm">
            Ces addons sont d&eacute;velopp&eacute;s par des fans et ne sont ni li&eacute;s, ni affili&eacute;s, ni approuv&eacute;s
            par Blizzard Entertainment, Inc. World of Warcraft est une marque d&eacute;pos&eacute;e de Blizzard Entertainment, Inc.
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
import { Head } from '@inertiajs/vue3';

defineProps({
    meta: { type: Object, required: true },
});

const addons = [
    {
        slug: 'maptidy',
        name: 'MapTidy',
        tagline: 'Filtrage des marqueurs de quêtes',
        image: '/images/addons/maptidy.png',
        curseforge: 'https://www.curseforge.com/wow/addons/maptidy',
        description:
            'MapTidy filtre les marqueurs de quêtes sur la carte du monde et la minicarte par '
            + '<strong class="text-white">type de quête</strong>. Tous les types sont visibles par défaut : '
            + 'l\'addon ne cache jamais un marqueur par erreur. Chaque filtre est mémorisé par personnage.',
        features: [
            'Affichage ou masquage des marqueurs par type de quête',
            'Option « masquer le déjà-fait par le bataillon », réglable type par type',
            'Préréglages nommés sauvegardés au niveau du compte, rappelables sur tous vos personnages',
            'Panneau redimensionnable et déplaçable (AceGUI-3.0), position mémorisée',
        ],
    },
    {
        slug: 'whattodo',
        name: 'WhatTodo',
        tagline: 'Liste de tâches à faire',
        image: '/images/addons/whattodo.png',
        curseforge: 'https://www.curseforge.com/wow/addons/whattodo',
        description:
            'WhatTodo affiche une <strong class="text-white">liste de tâches</strong> qui se '
            + 'réinitialise automatiquement selon la fréquence de chaque tâche. L\'état '
            + '« fait / à faire » est persisté par personnage.',
        features: [
            'Tâches quotidiennes, hebdomadaires et mensuelles',
            'Reset automatique à 5h (heure serveur) : chaque jour, chaque mercredi, le 1er du mois',
            'État recalculé tout seul au passage de l\'heure de reset',
            'Interface en français sur les clients FR, en anglais ailleurs',
        ],
    },
];
</script>
