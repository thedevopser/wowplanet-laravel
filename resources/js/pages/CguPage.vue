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

        <h1 class="text-2xl sm:text-3xl font-bold text-white">Conditions G&eacute;n&eacute;rales d'Utilisation</h1>
        <p class="text-xs text-slate-500">Derni&egrave;re mise &agrave; jour : 20 f&eacute;vrier 2026</p>

        <div class="bg-amber-500/10 border border-amber-500/20 rounded-lg p-4 text-amber-200 text-sm">
            WowPlanet est un site fan non officiel. Il n'est en aucun cas li&eacute;, affili&eacute; ou approuv&eacute; par Blizzard Entertainment, Inc. World of Warcraft et Blizzard Entertainment sont des marques d&eacute;pos&eacute;es de Blizzard Entertainment, Inc.
        </div>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">1. Objet</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                Les pr&eacute;sentes Conditions G&eacute;n&eacute;rales d'Utilisation (ci-apr&egrave;s &laquo;&nbsp;CGU&nbsp;&raquo;) ont pour objet de d&eacute;finir les modalit&eacute;s
                d'acc&egrave;s et d'utilisation du site <strong class="text-white">wowplanet.fr</strong> (ci-apr&egrave;s &laquo;&nbsp;WowPlanet&nbsp;&raquo;).
                En acc&eacute;dant au site, vous acceptez sans r&eacute;serve les pr&eacute;sentes CGU.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">2. Description du service</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                WowPlanet est un outil gratuit de suivi de progression pour le jeu World of Warcraft.
                Le service permet aux utilisateurs de&nbsp;:
            </p>
            <ul class="list-disc list-inside text-sm text-slate-300 space-y-1 ml-2">
                <li>Consulter la progression de leurs personnages (qu&ecirc;tes, hauts-faits, montures, mascottes, m&eacute;tiers)</li>
                <li>Comparer leurs accomplissements avec la base de donn&eacute;es compl&egrave;te du jeu</li>
                <li>Rechercher des personnages par royaume et nom</li>
            </ul>
            <p class="text-sm text-slate-300 leading-relaxed">
                Les donn&eacute;es de jeu sont r&eacute;cup&eacute;r&eacute;es exclusivement via l'API officielle de Blizzard Entertainment.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">3. Acc&egrave;s au service</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                L'acc&egrave;s au site est gratuit et ouvert &agrave; tous. Certaines fonctionnalit&eacute;s n&eacute;cessitent une connexion
                via Battle.net OAuth. WowPlanet se r&eacute;serve le droit de suspendre ou d'interrompre le service
                &agrave; tout moment, sans pr&eacute;avis ni indemnit&eacute;.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">4. Propri&eacute;t&eacute; intellectuelle</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                World of Warcraft, Blizzard Entertainment, Battle.net et tous les noms, personnages, images et
                &eacute;l&eacute;ments associ&eacute;s sont des marques d&eacute;pos&eacute;es et/ou des propri&eacute;t&eacute;s de Blizzard Entertainment, Inc.
            </p>
            <p class="text-sm text-slate-300 leading-relaxed">
                WowPlanet est un <strong class="text-white">site fan non officiel</strong> cr&eacute;&eacute; par des passionn&eacute;s.
                Il n'est en aucun cas li&eacute;, affili&eacute;, approuv&eacute; ou sponsoris&eacute; par Blizzard Entertainment, Inc.
                L'utilisation des noms, images et donn&eacute;es du jeu est faite dans le cadre du
                <em>fair use</em> et des conditions d'utilisation de l'API Blizzard.
            </p>
            <p class="text-sm text-slate-300 leading-relaxed">
                Le code source, le design et les contenus originaux du site WowPlanet sont la propri&eacute;t&eacute; de WowPlanet
                et ne peuvent &ecirc;tre reproduits sans autorisation.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">5. Responsabilit&eacute; et limitations</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                WowPlanet s'efforce de fournir des informations exactes et &agrave; jour, mais ne saurait &ecirc;tre tenu responsable&nbsp;:
            </p>
            <ul class="list-disc list-inside text-sm text-slate-300 space-y-1 ml-2">
                <li>Des erreurs ou omissions dans les donn&eacute;es affich&eacute;es</li>
                <li>De l'indisponibilit&eacute; temporaire ou permanente de l'API Blizzard</li>
                <li>Des dommages directs ou indirects li&eacute;s &agrave; l'utilisation du site</li>
                <li>Du contenu des sites tiers vers lesquels des liens sont propos&eacute;s (Wowhead, Blizzard, etc.)</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">6. Donn&eacute;es personnelles</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                Le traitement des donn&eacute;es personnelles est d&eacute;crit dans notre
                <router-link to="/privacy" class="text-blue-400 hover:underline">Politique de confidentialit&eacute;</router-link>.
                En utilisant le site, vous reconnaissez avoir pris connaissance de cette politique.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">7. Comportement des utilisateurs</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                Les utilisateurs s'engagent &agrave; ne pas utiliser le site de mani&egrave;re abusive, notamment&nbsp;:
            </p>
            <ul class="list-disc list-inside text-sm text-slate-300 space-y-1 ml-2">
                <li>Ne pas tenter d'acc&eacute;der de mani&egrave;re non autoris&eacute;e aux syst&egrave;mes du site</li>
                <li>Ne pas surcharger le service par des requ&ecirc;tes automatis&eacute;es excessives</li>
                <li>Ne pas utiliser le site &agrave; des fins ill&eacute;gales</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">8. Modification des CGU</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                WowPlanet se r&eacute;serve le droit de modifier les pr&eacute;sentes CGU &agrave; tout moment.
                Les modifications prennent effet d&egrave;s leur publication sur le site.
                L'utilisation continue du site apr&egrave;s modification vaut acceptation des nouvelles CGU.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">9. Droit applicable</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                Les pr&eacute;sentes CGU sont soumises au droit fran&ccedil;ais.
                En cas de litige, les parties s'efforceront de trouver une solution amiable.
                &Agrave; d&eacute;faut, les tribunaux fran&ccedil;ais seront seuls comp&eacute;tents.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg sm:text-xl font-semibold text-white">10. Contact</h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                Pour toute question concernant les pr&eacute;sentes CGU, vous pouvez nous contacter via notre
                <a href="https://discord.gg/wa49gGF8cr" target="_blank" rel="noopener noreferrer" class="text-blue-400 hover:underline">serveur Discord</a>.
            </p>
        </section>
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
