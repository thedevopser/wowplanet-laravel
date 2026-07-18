<template>
    <div>
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

        <LoadingSpinner v-if="store.loading" hint="Quêtes, hauts-faits, métiers, montures, mascottes..." />
        <div v-else-if="store.character" class="space-y-8 animate-in fade-in duration-500">
            <BreadcrumbNavInertia :crumbs="breadcrumbs" />
            <CharacterCard :character="store.character" />

            <!-- Content Type Tabs -->
            <div class="space-y-6">
                <div class="relative">
                <div ref="tabsContainer" role="tablist" aria-label="Sections du personnage" class="flex flex-wrap gap-1 sm:gap-2 border-b border-white/10 pb-1">
                    <button
                        v-for="tab in contentTabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        role="tab"
                        :aria-selected="activeTab === tab.id"
                        :id="'tab-' + tab.id"
                        :class="[
                            'px-3 sm:px-5 py-2 sm:py-2.5 rounded-t-xl text-xs sm:text-sm md:text-base font-bold transition-all border-b-2 -mb-[5px] whitespace-nowrap',
                            activeTab === tab.id
                                ? 'text-white border-blue-500 bg-slate-800/50'
                                : 'text-slate-500 border-transparent hover:text-slate-300 hover:border-slate-700'
                        ]"
                    >
                        {{ tab.label }}
                        <span v-if="tab.count !== undefined" class="ml-1 sm:ml-2 text-[10px] sm:text-xs font-mono opacity-60">{{ tab.count }}</span>
                    </button>
                </div>
                </div>

                <ScoreTab v-if="activeTab === 'score'" />
                <MythicPlusTab v-if="activeTab === 'mythicplus'" />
                <RaidsTab v-if="activeTab === 'raids'" />
                <QuestsTab v-if="activeTab === 'quests'" />
                <AchievementsTab v-if="activeTab === 'achievements'" />
                <ReputationsTab v-if="activeTab === 'reputations'" />
                <ProfessionsTab v-if="activeTab === 'professions'" />
                <MountsTab v-if="activeTab === 'mounts'" :character="store.character" />
                <PetsTab v-if="activeTab === 'pets'" :character="store.character" />
                <DecorTab v-if="activeTab === 'decor'" :character="store.character" />
                <TransmogTab v-if="activeTab === 'transmog'" :character="store.character" />
                <EquipmentTab v-if="activeTab === 'equipment'" :character="store.character" />
            </div>
        </div>
        <div v-else class="max-w-xl mx-auto text-center py-16 space-y-3">
            <BreadcrumbNavInertia :crumbs="[{ label: notFoundName }]" />
            <h1 class="text-2xl font-black text-slate-200">Personnage introuvable</h1>
            <p class="text-slate-400">Impossible de récupérer le personnage {{ notFoundName }}. Vérifiez le nom et le royaume.</p>
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
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useCharacterStore } from '../stores/character';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import CharacterCard from '../components/CharacterCard.vue';
import BreadcrumbNavInertia from '../components/inertia/BreadcrumbNavInertia.vue';
import QuestsTab from '../components/QuestsTab.vue';
import AchievementsTab from '../components/AchievementsTab.vue';
import ReputationsTab from '../components/ReputationsTab.vue';
import MountsTab from '../components/MountsTab.vue';
import PetsTab from '../components/PetsTab.vue';
import ProfessionsTab from '../components/ProfessionsTab.vue';
import DecorTab from '../components/DecorTab.vue';
import TransmogTab from '../components/TransmogTab.vue';
import ScoreTab from '../components/ScoreTab.vue';
import MythicPlusTab from '../components/MythicPlusTab.vue';
import RaidsTab from '../components/RaidsTab.vue';
import EquipmentTab from '../components/EquipmentTab.vue';

const props = defineProps({
    character: { type: Object, default: null },
    realm: { type: String, required: true },
    name: { type: String, required: true },
    meta: { type: Object, required: true },
});

const store = useCharacterStore();
const activeTab = ref('score');
const tabsContainer = ref(null);

// Amorce le store depuis les props serveur (synchrone => rendu SSR immédiat).
function seedCharacter(character) {
    store.character = character;
    store.loading = false;
    store.error = null;
}
seedCharacter(props.character);

const notFoundName = computed(() => props.name ? props.name.charAt(0).toUpperCase() + props.name.slice(1) : '');

const breadcrumbs = computed(() => {
    const crumbs = [];
    if (store.isAuthenticated) {
        crumbs.push({ label: 'Mes personnages', to: '/my-characters' });
    }
    if (store.character) {
        crumbs.push({ label: store.character.name });
    }
    return crumbs;
});

const contentTabs = computed(() => [
    { id: 'score', label: 'Score', count: undefined },
    { id: 'quests', label: 'Quêtes', count: undefined },
    { id: 'achievements', label: 'Hauts-faits', count: undefined },
    { id: 'reputations', label: 'Réputations', count: undefined },
    { id: 'professions', label: 'Métiers', count: store.character?.professions?.length || undefined },
    { id: 'mythicplus', label: 'Mythique+', count: store.character?.mythicKeystone?.rating ? Math.round(store.character.mythicKeystone.rating) : undefined },
    { id: 'raids', label: 'Raids', count: store.character?.raidsCount || undefined },
    { id: 'equipment', label: 'Équipement', count: undefined },
    { id: 'mounts', label: 'Montures', count: store.character?.mountsCount },
    { id: 'pets', label: 'Mascottes', count: store.character?.petsCount },
    { id: 'decor', label: 'Décorations', count: store.character?.decorCount },
    { id: 'transmog', label: 'Garde-robe', count: store.character?.appearancesCount },
]);

// Sur navigation Inertia vers un autre personnage, le composant est réutilisé :
// on ré-amorce le store et on réinitialise l'onglet.
watch(() => props.character, (character) => {
    activeTab.value = 'score';
    seedCharacter(character);
});

onMounted(() => {
    if (store.isAuthenticated && store.crossCharacterStatus !== 'ready') {
        store.loadCrossCharacterData();
    }
});
</script>
