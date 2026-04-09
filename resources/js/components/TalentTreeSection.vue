<template>
    <div class="mt-6">
        <!-- Toggle button -->
        <button
            @click="toggle"
            class="w-full flex items-center justify-between px-5 py-4 rounded-2xl border transition-all"
            :class="[
                expanded
                    ? 'bg-slate-800/60 border-emerald-500/30 shadow-lg shadow-emerald-500/5'
                    : 'bg-slate-800/30 border-white/5 hover:border-white/10 hover:bg-slate-800/40',
            ]"
        >
            <div class="flex items-center gap-3">
                <div class="w-2 h-6 bg-emerald-500 rounded-full shadow-lg shadow-emerald-500/50"></div>
                <span class="text-lg font-black text-white">Talents</span>
                <span v-if="talentData" class="text-sm text-slate-400 font-medium">{{ talentData.spec_name }}</span>
            </div>
            <svg
                class="w-5 h-5 text-slate-400 transition-transform"
                :class="expanded ? 'rotate-180' : ''"
                fill="none" viewBox="0 0 24 24" stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Expanded content -->
        <div v-if="expanded" class="mt-4 space-y-4">
            <!-- Loading -->
            <div v-if="loading" class="flex justify-center py-12">
                <div class="animate-spin w-8 h-8 border-2 border-emerald-500 border-t-transparent rounded-full"></div>
            </div>

            <!-- Error -->
            <div v-else-if="error" class="card-glass rounded-2xl border p-6 text-center">
                <p class="text-slate-400 text-sm">{{ error }}</p>
            </div>

            <!-- Talent trees -->
            <template v-else-if="talentData">
                <!-- Sub-tabs -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        :class="[
                            'px-4 py-2 rounded-xl text-sm font-bold transition-all border',
                            activeTab === tab.key
                                ? 'bg-emerald-600 border-emerald-400 text-white shadow-lg shadow-emerald-500/20'
                                : 'bg-slate-800/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-700 hover:border-white/10',
                        ]"
                    >{{ tab.label }}</button>
                </div>

                <!-- Tree content -->
                <div class="card-glass rounded-2xl border p-4 sm:p-6 overflow-hidden">
                    <TalentTreeGrid
                        v-if="activeTab === 'class'"
                        :nodes="talentData.class_nodes"
                    />
                    <TalentTreeGrid
                        v-else-if="activeTab === 'spec'"
                        :nodes="talentData.spec_nodes"
                    />
                    <template v-else-if="activeTab === 'hero'">
                        <!-- Hero tree selector if multiple -->
                        <div v-if="talentData.hero_trees.length > 1" class="flex gap-2 mb-4">
                            <button
                                v-for="tree in talentData.hero_trees"
                                :key="tree.id"
                                @click="activeHeroTreeId = tree.id"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition-all border',
                                    activeHeroTreeId === tree.id
                                        ? (tree.active ? 'bg-yellow-600/80 border-yellow-400 text-white' : 'bg-slate-600 border-slate-400 text-white')
                                        : 'bg-slate-800/80 border-white/5 text-slate-500 hover:text-white',
                                ]"
                            >
                                {{ tree.name }}
                                <span v-if="tree.active" class="ml-1 text-yellow-300">&#9733;</span>
                            </button>
                        </div>
                        <TalentTreeGrid
                            v-if="activeHeroTree"
                            :nodes="activeHeroTree.nodes"
                        />
                    </template>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useWowheadTooltips } from '../composables/useWowheadTooltips';
import TalentTreeGrid from './TalentTreeGrid.vue';
import axios from 'axios';

useWowheadTooltips();

const props = defineProps({
    realm: { type: String, required: true },
    name: { type: String, required: true },
});

const expanded = ref(false);
const loading = ref(false);
const error = ref(null);
const talentData = ref(null);
const activeTab = ref('class');
const activeHeroTreeId = ref(null);
let fetched = false;

const tabs = computed(() => {
    const list = [
        { key: 'class', label: talentData.value ? talentData.value.class_name : 'Classe' },
        { key: 'spec', label: talentData.value ? talentData.value.spec_name : 'Spécialisation' },
    ];

    if (talentData.value?.hero_trees?.length) {
        list.push({ key: 'hero', label: 'Talents héroïques' });
    }

    return list;
});

const activeHeroTree = computed(() => {
    if (!talentData.value?.hero_trees?.length) return null;
    return talentData.value.hero_trees.find(t => t.id === activeHeroTreeId.value) || talentData.value.hero_trees[0];
});

async function fetchTalents() {
    if (fetched) return;
    fetched = true;
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(`/api/character/${encodeURIComponent(props.realm)}/${encodeURIComponent(props.name)}/talents`);
        talentData.value = response.data;

        // Default to the active hero tree
        const activeTree = talentData.value.hero_trees?.find(t => t.active);
        if (activeTree) {
            activeHeroTreeId.value = activeTree.id;
        } else if (talentData.value.hero_trees?.length) {
            activeHeroTreeId.value = talentData.value.hero_trees[0].id;
        }
    } catch (e) {
        error.value = 'Impossible de charger les talents.';
        fetched = false;
    } finally {
        loading.value = false;
    }
}

function toggle() {
    expanded.value = !expanded.value;
    if (expanded.value && !fetched) {
        fetchTalents();
    }
}
</script>
