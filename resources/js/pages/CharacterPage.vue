<template>
    <LoadingSpinner v-if="store.loading" hint="Quêtes, hauts-faits, métiers, montures, mascottes..." />
    <div v-else-if="store.character" class="space-y-8 animate-in fade-in duration-500">
        <BreadcrumbNav :crumbs="breadcrumbs" />
        <CharacterCard :character="store.character" />

        <!-- Content Type Tabs -->
        <div class="space-y-6">
            <div class="relative">
            <div v-if="canScrollLeft" class="absolute left-0 top-0 bottom-0 w-8 bg-gradient-to-r from-slate-900 to-transparent z-10 pointer-events-none"></div>
            <div v-if="canScrollRight" class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-slate-900 to-transparent z-10 pointer-events-none"></div>
            <div ref="tabsContainer" @scroll="updateScrollIndicators" role="tablist" aria-label="Sections du personnage" class="flex gap-1 sm:gap-2 border-b border-white/10 pb-1 overflow-x-auto no-scrollbar">
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
            <QuestsTab v-if="activeTab === 'quests'" />
            <AchievementsTab v-if="activeTab === 'achievements'" />
            <ReputationsTab v-if="activeTab === 'reputations'" />
            <ProfessionsTab v-if="activeTab === 'professions'" />
            <MountsTab v-if="activeTab === 'mounts'" :character="store.character" />
            <PetsTab v-if="activeTab === 'pets'" :character="store.character" />
            <DecorTab v-if="activeTab === 'decor'" :character="store.character" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { useCharacterStore } from '../stores/character';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import CharacterCard from '../components/CharacterCard.vue';
import BreadcrumbNav from '../components/BreadcrumbNav.vue';
import QuestsTab from '../components/QuestsTab.vue';
import AchievementsTab from '../components/AchievementsTab.vue';
import ReputationsTab from '../components/ReputationsTab.vue';
import MountsTab from '../components/MountsTab.vue';
import PetsTab from '../components/PetsTab.vue';
import ProfessionsTab from '../components/ProfessionsTab.vue';
import DecorTab from '../components/DecorTab.vue';
import ScoreTab from '../components/ScoreTab.vue';
import MythicPlusTab from '../components/MythicPlusTab.vue';

const route = useRoute();
const store = useCharacterStore();
const activeTab = ref('score');
const tabsContainer = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

function updateScrollIndicators() {
    const el = tabsContainer.value;
    if (!el) return;
    canScrollLeft.value = el.scrollLeft > 4;
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
}

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
    { id: 'mounts', label: 'Montures', count: store.character?.mountsCount },
    { id: 'pets', label: 'Mascottes', count: store.character?.petsCount },
    { id: 'decor', label: 'Décorations', count: store.character?.decorCount },
]);

const loadCharacter = () => {
    const { realm, name } = route.params;
    if (realm && name) {
        store.fetchCharacter(realm, name);
    }
};

onMounted(() => {
    loadCharacter();
    nextTick(updateScrollIndicators);
    if (store.isAuthenticated && store.crossCharacterStatus !== 'ready') {
        store.loadCrossCharacterData();
    }
});

watch(() => route.params, (newParams, oldParams) => {
    if (newParams.realm !== oldParams?.realm || newParams.name !== oldParams?.name) {
        activeTab.value = 'score';
        loadCharacter();
    }
});

watch(() => store.character, (char) => {
    if (char) {
        document.title = `${char.name} - ${char.class} ${char.level} | ${char.realm} | WowPlanet`;
        nextTick(updateScrollIndicators);
    }
});
</script>
