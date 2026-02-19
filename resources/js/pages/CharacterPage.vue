<template>
    <LoadingSpinner v-if="store.loading" hint="Quêtes, hauts-faits, métiers, montures, mascottes..." />
    <div v-else-if="store.character" class="space-y-8 animate-in fade-in duration-500">
        <CharacterCard :character="store.character" />

        <!-- Content Type Tabs -->
        <div class="space-y-6">
            <div class="flex gap-1 sm:gap-2 border-b border-white/10 pb-1 overflow-x-auto no-scrollbar">
                <button
                    v-for="tab in contentTabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
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

            <QuestsTab v-if="activeTab === 'quests'" />
            <AchievementsTab v-if="activeTab === 'achievements'" />
            <ProfessionsTab v-if="activeTab === 'professions'" />
            <MountsTab v-if="activeTab === 'mounts'" :character="store.character" />
            <PetsTab v-if="activeTab === 'pets'" :character="store.character" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useCharacterStore } from '../stores/character';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import CharacterCard from '../components/CharacterCard.vue';
import QuestsTab from '../components/QuestsTab.vue';
import AchievementsTab from '../components/AchievementsTab.vue';
import MountsTab from '../components/MountsTab.vue';
import PetsTab from '../components/PetsTab.vue';
import ProfessionsTab from '../components/ProfessionsTab.vue';

const route = useRoute();
const store = useCharacterStore();
const activeTab = ref('quests');

const contentTabs = computed(() => [
    { id: 'quests', label: 'Quêtes', count: undefined },
    { id: 'achievements', label: 'Hauts-faits', count: undefined },
    { id: 'professions', label: 'Métiers', count: store.character?.professions?.length || undefined },
    { id: 'mounts', label: 'Montures', count: store.character?.mountsCount },
    { id: 'pets', label: 'Mascottes', count: store.character?.petsCount },
]);

const loadCharacter = () => {
    const { realm, name } = route.params;
    if (realm && name) {
        store.fetchCharacter(realm, name);
    }
};

onMounted(loadCharacter);

watch(() => route.params, (newParams, oldParams) => {
    if (newParams.realm !== oldParams?.realm || newParams.name !== oldParams?.name) {
        activeTab.value = 'quests';
        loadCharacter();
    }
});

watch(() => store.character, (char) => {
    if (char) {
        document.title = `${char.name} - ${char.class} ${char.level} | ${char.realm} | WowPlanet`;
    }
});
</script>
