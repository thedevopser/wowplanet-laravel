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
                <span class="bg-clip-text text-transparent bg-linear-to-r from-blue-200 via-blue-400 to-blue-600">Foire aux questions</span>
            </h1>
            <p class="text-base sm:text-lg text-slate-400 leading-relaxed">
                Tout ce que vous devez savoir pour profiter pleinement de WowPlanet.
            </p>
        </div>

        <div class="max-w-4xl mx-auto space-y-4 sm:space-y-6">
            <!-- Q1 : Qu'est-ce que WowPlanet ? -->
            <section class="card-glass rounded-2xl border p-5 sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 bg-blue-600/10 border border-blue-500/20 rounded-xl flex items-center justify-center text-blue-400">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A8.966 8.966 0 0 1 3 12c0-1.97.633-3.793 1.708-5.273" />
                        </svg>
                    </div>
                    <div class="space-y-3">
                        <h2 class="text-lg sm:text-xl font-bold text-white">Qu&rsquo;est-ce que WowPlanet&nbsp;?</h2>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            WowPlanet est un <strong class="text-white">site fan gratuit et non officiel</strong> de suivi de progression
                            pour World of Warcraft. Il vous permet de visualiser en d&eacute;tail la progression de vos personnages
                            et de comparer vos accomplissements avec la base de donn&eacute;es compl&egrave;te du jeu &mdash; enti&egrave;rement en fran&ccedil;ais.
                        </p>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            Les donn&eacute;es sont issues de l&rsquo;API officielle Blizzard et couvrent&nbsp;:
                        </p>
                        <ul class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-sm text-slate-300">
                            <li class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                Montures (1&nbsp;500+)
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 shrink-0"></span>
                                Mascottes (2&nbsp;100+)
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                Hauts-faits (8&nbsp;600+)
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 shrink-0"></span>
                                Qu&ecirc;tes (21&nbsp;000+)
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-violet-400 shrink-0"></span>
                                D&eacute;corations
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                                M&eacute;tiers &amp; R&eacute;putations
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Q2 : Comment importer mes personnages ? -->
            <section class="card-glass rounded-2xl border p-5 sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 bg-amber-600/10 border border-amber-500/20 rounded-xl flex items-center justify-center text-amber-400">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                    </div>
                    <div class="space-y-3">
                        <h2 class="text-lg sm:text-xl font-bold text-white">Comment importer mes personnages&nbsp;?</h2>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            Cliquez sur <strong class="text-white">&laquo;&nbsp;Se connecter avec Battle.net&nbsp;&raquo;</strong> en haut du site.
                            Une fois authentifi&eacute;, <strong class="text-white">tous vos personnages</strong> sont automatiquement import&eacute;s et synchronis&eacute;s.
                            Aucune configuration manuelle n&rsquo;est n&eacute;cessaire.
                        </p>
                        <div class="bg-amber-500/5 border border-amber-500/10 rounded-lg p-3 sm:p-4">
                            <p class="text-sm text-amber-200/80 leading-relaxed">
                                <strong class="text-amber-300">Cross-personnage&nbsp;:</strong>
                                Les hauts-faits et les r&eacute;putations sont agr&eacute;g&eacute;s au niveau du compte (<em>account-wide</em>).
                                Pour les qu&ecirc;tes et les recettes de m&eacute;tiers, WowPlanet vous indique sur quel autre personnage
                                un &eacute;l&eacute;ment est d&eacute;j&agrave; compl&eacute;t&eacute; gr&acirc;ce &agrave; la mention
                                <strong class="text-amber-300">&laquo;&nbsp;disponible sur un autre personnage&nbsp;&raquo;</strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Q3 : Qu'est-ce que le score compte ? -->
            <section class="card-glass rounded-2xl border p-5 sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 bg-emerald-600/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                        </svg>
                    </div>
                    <div class="space-y-3">
                        <h2 class="text-lg sm:text-xl font-bold text-white">Qu&rsquo;est-ce que le score compte&nbsp;?</h2>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            Le score compte est une <strong class="text-white">note globale sur 100</strong> qui &eacute;value votre progression
                            sur l&rsquo;ensemble de vos personnages. Il est calcul&eacute; sur <strong class="text-white">7&nbsp;dimensions</strong>&nbsp;:
                        </p>
                        <ul class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                            <li class="px-3 py-1.5 bg-slate-800/60 rounded-lg text-slate-300 text-center">Qu&ecirc;tes</li>
                            <li class="px-3 py-1.5 bg-slate-800/60 rounded-lg text-slate-300 text-center">Hauts-faits</li>
                            <li class="px-3 py-1.5 bg-slate-800/60 rounded-lg text-slate-300 text-center">Montures</li>
                            <li class="px-3 py-1.5 bg-slate-800/60 rounded-lg text-slate-300 text-center">Mascottes</li>
                            <li class="px-3 py-1.5 bg-slate-800/60 rounded-lg text-slate-300 text-center">M&eacute;tiers</li>
                            <li class="px-3 py-1.5 bg-slate-800/60 rounded-lg text-slate-300 text-center">D&eacute;corations</li>
                            <li class="px-3 py-1.5 bg-slate-800/60 rounded-lg text-slate-300 text-center sm:col-span-2">R&eacute;putations</li>
                        </ul>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            Le r&eacute;sultat est visualis&eacute; sous forme de <strong class="text-white">radar chart</strong> et peut &ecirc;tre
                            <strong class="text-white">partag&eacute; sur Discord</strong>.
                            Acc&eacute;dez-y via le menu <strong class="text-white">&laquo;&nbsp;Mon score&nbsp;&raquo;</strong> apr&egrave;s connexion.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Q4 : Peut-on utiliser le site sans se connecter ? -->
            <section class="card-glass rounded-2xl border p-5 sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 bg-violet-600/10 border border-violet-500/20 rounded-xl flex items-center justify-center text-violet-400">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                    <div class="space-y-3">
                        <h2 class="text-lg sm:text-xl font-bold text-white">Peut-on utiliser le site sans se connecter&nbsp;?</h2>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            <strong class="text-white">Oui, absolument.</strong> Plusieurs fonctionnalit&eacute;s sont accessibles sans connexion Battle.net&nbsp;:
                        </p>
                        <ul class="space-y-2 text-sm text-slate-300">
                            <li class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-violet-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                La <a href="/base-de-donnees" class="text-blue-400 hover:underline">base de donn&eacute;es compl&egrave;te</a> (montures, hauts-faits, qu&ecirc;tes, mascottes, d&eacute;corations, professions)
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-violet-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                La recherche de <strong class="text-white">n&rsquo;importe quel personnage</strong> par royaume et nom via la barre de recherche en haut du site
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-violet-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                La consultation des profils publics de personnages
                            </li>
                        </ul>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            La connexion Battle.net est n&eacute;cessaire uniquement pour importer vos propres personnages,
                            acc&eacute;der au score compte et g&eacute;rer vos t&acirc;ches.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Q5 : Comment fonctionnent les tâches quotidiennes ? -->
            <section class="card-glass rounded-2xl border p-5 sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 bg-cyan-600/10 border border-cyan-500/20 rounded-xl flex items-center justify-center text-cyan-400">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="space-y-3">
                        <h2 class="text-lg sm:text-xl font-bold text-white">Comment fonctionnent les t&acirc;ches quotidiennes&nbsp;?</h2>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            WowPlanet vous permet de cr&eacute;er des <strong class="text-white">t&acirc;ches personnalis&eacute;es</strong> pour chacun de vos personnages
                            afin de suivre vos objectifs r&eacute;currents (coffres de cl&eacute;s mythiques, qu&ecirc;tes mondiales, etc.).
                        </p>
                        <ul class="space-y-2 text-sm text-slate-300">
                            <li class="flex items-start gap-2">
                                <span class="px-2 py-0.5 bg-cyan-500/10 text-cyan-400 rounded text-xs font-semibold shrink-0 mt-0.5">Daily</span>
                                R&eacute;initialis&eacute;es automatiquement chaque jour &agrave; 5h du matin
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="px-2 py-0.5 bg-cyan-500/10 text-cyan-400 rounded text-xs font-semibold shrink-0 mt-0.5">Weekly</span>
                                R&eacute;initialis&eacute;es automatiquement chaque mercredi &agrave; 5h du matin
                            </li>
                        </ul>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            Acc&eacute;dez &agrave; vos t&acirc;ches via le <strong class="text-white">bouton en bas &agrave; droite</strong> de l&rsquo;&eacute;cran.
                            Un compteur vous indique le nombre de t&acirc;ches en attente.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <!-- CTA Discord -->
        <div class="max-w-2xl mx-auto text-center">
            <div class="card-glass rounded-2xl border p-6 sm:p-8">
                <p class="text-sm sm:text-base text-slate-300 mb-4">
                    Une question qui n&rsquo;est pas list&eacute;e ici&nbsp;?
                </p>
                <a
                    href="https://discord.gg/wa49gGF8cr"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#5865F2]/20 border border-[#5865F2]/30 text-[#5865F2] hover:bg-[#5865F2]/30 rounded-lg font-semibold text-sm transition-colors"
                >
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.095 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.095 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                    </svg>
                    Rejoignez-nous sur Discord
                </a>
            </div>
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
</script>
