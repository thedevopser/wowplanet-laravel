<template>
    <div class="space-y-8 animate-in fade-in duration-500">
        <div class="text-center max-w-3xl mx-auto">
            <h2 class="text-3xl md:text-4xl font-black mb-3">
                <span class="bg-clip-text text-transparent bg-linear-to-r from-blue-200 via-blue-400 to-blue-600">Mon score compte</span>
            </h2>
            <p class="text-slate-400 text-sm md:text-base">Score de completion agrege de tous vos personnages</p>
        </div>

        <!-- Computing progress -->
        <div v-if="status === 'computing'" class="card-glass rounded-2xl border p-5 sm:p-8 text-center space-y-4">
            <div class="text-lg font-bold text-white">
                Analyse de {{ progress.current }}...
            </div>
            <div class="text-slate-500 text-sm">
                {{ progress.loaded }} / {{ progress.total }} personnages charges
                <span v-if="progress.errors > 0" class="text-amber-400 ml-1">
                    ({{ progress.errors }} erreur{{ progress.errors > 1 ? 's' : '' }})
                </span>
            </div>
            <div class="h-3 bg-slate-800 rounded-full overflow-hidden border border-white/5 max-w-md mx-auto">
                <div
                    class="h-full bg-linear-to-r from-blue-700 via-blue-500 to-blue-400 transition-all duration-500 relative"
                    :style="{ width: progressPercent + '%' }"
                >
                    <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                </div>
            </div>
            <p class="text-slate-600 text-xs">Le resultat sera mis en cache pour 24h</p>
        </div>

        <!-- Initial loading -->
        <LoadingSpinner
            v-else-if="status === 'loading'"
            title="Chargement en cours..."
            subtitle="Recuperation de vos donnees"
        />

        <!-- Error -->
        <div v-else-if="status === 'error'" class="text-center py-16">
            <p class="text-red-400 mb-4">{{ errorMessage }}</p>
            <button @click="startPolling" class="text-sm text-blue-400 hover:underline">Reessayer</button>
        </div>

        <!-- Results -->
        <template v-else-if="status === 'ready' && score">
            <!-- Radar + Global Score -->
            <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/5 blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10 flex flex-col items-center gap-6">
                    <div class="text-center">
                        <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center justify-center gap-3">
                            <div class="w-2 h-6 sm:h-8 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50"></div>
                            Score Compte
                        </h3>
                        <p class="text-slate-500 text-xs sm:text-sm mt-1">
                            Agrege sur {{ characterCount }} personnage{{ characterCount > 1 ? 's' : '' }}
                            <span v-if="cachedAt" class="ml-2 text-slate-600">· maj {{ cachedAtFormatted }}</span>
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-6 sm:gap-10 w-full justify-center">
                        <ScoreRadar :axes="radarAxes" :size="280" />
                        <div class="flex flex-col items-center gap-2">
                            <div class="text-5xl sm:text-6xl font-black tabular-nums" :style="{ color: globalColor }">
                                {{ score.global }}
                            </div>
                            <div class="text-xs sm:text-sm font-bold text-slate-500 uppercase tracking-widest">/ 100</div>
                            <div class="mt-2 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider border" :class="rankClass">
                                {{ rank }}
                            </div>
                            <button
                                @click="showShareModal = true"
                                class="mt-3 px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors flex items-center gap-2 border border-indigo-400/30"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                                Partager sur Discord
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dimension Cards -->
            <section>
                <h4 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1 mb-4 sm:mb-6">
                    Detail par dimension
                    <div class="flex-1 h-px bg-slate-700"></div>
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div
                        v-for="dim in dimensionCards"
                        :key="dim.key"
                        class="bg-slate-800/40 border border-white/5 p-4 rounded-2xl"
                    >
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-1.5 h-5 rounded-full" :style="{ backgroundColor: dim.color }"></div>
                                <span class="text-sm font-bold text-slate-300">{{ dim.label }}</span>
                            </div>
                            <span class="text-lg font-black tabular-nums" :style="{ color: dim.color }">
                                {{ Math.round(dim.score) }}%
                            </span>
                        </div>
                        <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden mb-2">
                            <div
                                class="h-full rounded-full transition-all duration-1000"
                                :style="{ width: dim.score + '%', backgroundColor: dim.color }"
                            ></div>
                        </div>
                        <div class="text-[10px] sm:text-xs font-mono text-slate-500">
                            {{ dim.completed.toLocaleString('fr-FR') }} / {{ dim.total.toLocaleString('fr-FR') }}
                        </div>
                    </div>
                </div>
            </section>

            <!-- Recommendations -->
            <section v-if="recommendations.length">
                <h4 class="text-xs sm:text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1 mb-2 sm:mb-4">
                    Il vous reste...
                    <div class="flex-1 h-px bg-slate-700"></div>
                </h4>
                <p class="text-slate-500 text-xs sm:text-sm mb-4">Categories les plus proches de la completion sur l'ensemble de vos personnages.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="rec in recommendations"
                        :key="rec.key"
                        class="bg-slate-800/40 border border-white/5 rounded-xl overflow-hidden hover:border-blue-500/20 transition-colors"
                    >
                        <div
                            @click="toggleRec(rec.key)"
                            class="p-4 flex items-center gap-3 cursor-pointer group"
                        >
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-black shrink-0"
                                :style="{ backgroundColor: rec.color + '20', color: rec.color }">
                                {{ rec.missing }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-slate-300 group-hover:text-blue-400 transition-colors truncate">{{ rec.name }}</div>
                                <div class="text-[10px] sm:text-xs text-slate-500 font-mono">
                                    {{ rec.completed }}/{{ rec.total }} — {{ rec.dimension }}
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <div class="h-1.5 w-16 bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" :style="{ width: rec.percent + '%', backgroundColor: rec.color }"></div>
                                </div>
                                <span class="text-[10px] text-slate-600 transition-transform" :class="expandedRec === rec.key ? 'rotate-180' : ''">&#9660;</span>
                            </div>
                        </div>

                        <div v-if="expandedRec === rec.key" class="px-4 pb-4 border-t border-white/5 animate-in slide-in-from-top-2 duration-300">
                            <div class="mt-3 space-y-1 max-h-64 overflow-y-auto no-scrollbar">
                                <div v-for="item in rec.missingItems" :key="item.id || item.name" class="flex items-center gap-2 text-xs sm:text-sm py-1">
                                    <span class="text-slate-700 shrink-0">&cir;</span>
                                    <a :href="item.wowheadUrl" target="_blank" rel="noopener" class="text-slate-400 hover:text-blue-400 hover:underline truncate flex-1">{{ item.name }}</a>
                                </div>
                                <div v-if="rec.missingMore > 0" class="text-[10px] text-slate-600 font-mono pt-1">
                                    ... et {{ rec.missingMore }} autre{{ rec.missingMore > 1 ? 's' : '' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Refresh -->
            <div class="text-center">
                <button @click="refresh" class="text-xs text-slate-600 hover:text-slate-400 transition-colors underline">
                    Recalculer (vider le cache)
                </button>
            </div>
        </template>

        <!-- Empty / not authenticated -->
        <div v-else-if="status === 'ready'" class="text-center py-16">
            <p class="text-slate-500">Aucun personnage trouve. Connectez-vous avec Battle.net pour acceder a cette page.</p>
        </div>

        <ShareScoreModal :show="showShareModal" variant="account" :score-data="shareData" @close="showShareModal = false" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { computeScore, getScoreColor, DIMENSION_LABELS, DIMENSION_COLORS, WEIGHTS } from '../utils/scoreCalculator';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import ScoreRadar from '../components/ScoreRadar.vue';
import ShareScoreModal from '../components/ShareScoreModal.vue';

const status = ref('loading');
const progress = ref({ loaded: 0, errors: 0, total: 0, current: '' });
const virtualProfile = ref(null);
const characterCount = ref(0);
const cachedAt = ref(null);
const errorMessage = ref('');
const showShareModal = ref(false);
const expandedRec = ref(null);
let pollTimer = null;

const score = computed(() => virtualProfile.value ? computeScore(virtualProfile.value) : null);

const progressPercent = computed(() => {
    if (!progress.value.total) return 0;
    return ((progress.value.loaded + progress.value.errors) / progress.value.total) * 100;
});

const globalColor = computed(() => getScoreColor(score.value?.global || 0));

const radarAxes = computed(() => {
    if (!score.value) return [];
    return Object.entries(score.value.dimensions).map(([key, dim]) => ({
        label: DIMENSION_LABELS[key],
        score: dim.score,
    }));
});

const rank = computed(() => {
    const s = score.value?.global || 0;
    if (s >= 90) return 'Legendaire';
    if (s >= 75) return 'Epique';
    if (s >= 50) return 'Rare';
    if (s >= 25) return 'Commun';
    return 'Debutant';
});

const rankClass = computed(() => {
    const s = score.value?.global || 0;
    if (s >= 90) return 'bg-orange-500/10 text-orange-400 border-orange-500/30';
    if (s >= 75) return 'bg-purple-500/10 text-purple-400 border-purple-500/30';
    if (s >= 50) return 'bg-blue-500/10 text-blue-400 border-blue-500/30';
    if (s >= 25) return 'bg-green-500/10 text-green-400 border-green-500/30';
    return 'bg-slate-500/10 text-slate-400 border-slate-500/30';
});

const cachedAtFormatted = computed(() => {
    if (!cachedAt.value) return '';
    const d = new Date(cachedAt.value);
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
});

const dimensionCards = computed(() => {
    if (!score.value) return [];
    return Object.entries(score.value.dimensions).map(([key, dim]) => ({
        key,
        label: DIMENSION_LABELS[key],
        color: DIMENSION_COLORS[key],
        score: dim.score,
        completed: dim.completed,
        total: dim.total,
        weightLabel: Math.round(WEIGHTS[key] * 100) + '%',
    }));
});

const MAX_ITEMS_SHOWN = 20;

function wowheadUrl(type, item) {
    const base = 'https://www.wowhead.com/fr';
    if (type === 'mount') return item.wowhead_id ? `${base}/spell=${item.wowhead_id}` : `${base}/search?q=${encodeURIComponent(item.name)}`;
    if (type === 'pet') return item.creature_id ? `${base}/npc=${item.creature_id}` : `${base}/search?q=${encodeURIComponent(item.name)}`;
    if (type === 'quest') return `${base}/quest=${item.id}`;
    if (type === 'achievement') return `${base}/achievement=${item.id}`;
    if (type === 'decor') return item.item_id ? `${base}/item=${item.item_id}` : `${base}/search?q=${encodeURIComponent(item.name)}`;
    return `${base}/search?q=${encodeURIComponent(item.name)}`;
}

function buildGroupRecs(items, type, dimension, color, groupKey) {
    const groups = {};
    for (const item of items) {
        const group = item[groupKey];
        if (!group) continue;
        if (!groups[group]) groups[group] = { completed: [], missing: [] };
        if (item.is_completed) groups[group].completed.push(item);
        else groups[group].missing.push(item);
    }
    const recs = [];
    for (const [name, data] of Object.entries(groups)) {
        const total = data.completed.length + data.missing.length;
        if (data.missing.length === 0 || data.completed.length === 0) continue;
        recs.push({
            key: `${type}:${name}`,
            name, dimension, color,
            completed: data.completed.length, total,
            missing: data.missing.length,
            percent: (data.completed.length / total) * 100,
            missingItems: data.missing.slice(0, MAX_ITEMS_SHOWN).map(i => ({ id: i.id, name: i.name, wowheadUrl: wowheadUrl(type, i) })),
            missingMore: Math.max(0, data.missing.length - MAX_ITEMS_SHOWN),
        });
    }
    return recs;
}

const recommendations = computed(() => {
    if (!virtualProfile.value) return [];
    const vp = virtualProfile.value;
    const recs = [];

    recs.push(...buildGroupRecs(vp.mounts || [], 'mount', 'Montures', DIMENSION_COLORS.mounts, 'source'));
    recs.push(...buildGroupRecs(vp.pets || [], 'pet', 'Mascottes', DIMENSION_COLORS.pets, 'source'));
    recs.push(...buildGroupRecs(vp.decor || [], 'decor', 'Decorations', DIMENSION_COLORS.decor, 'source'));

    for (const expId in (vp.collections || {})) {
        for (const cat of (vp.collections[expId]?.achievements?.categories || [])) {
            if (!cat.items) continue;
            const missing = cat.items.filter(i => !i.is_completed);
            const completed = cat.items.filter(i => i.is_completed);
            if (missing.length === 0 || completed.length === 0) continue;
            recs.push({
                key: `ach:${expId}:${cat.name}`, name: cat.name, dimension: 'Hauts-faits', color: DIMENSION_COLORS.achievements,
                completed: completed.length, total: cat.items.length, missing: missing.length,
                percent: (completed.length / cat.items.length) * 100,
                missingItems: missing.slice(0, MAX_ITEMS_SHOWN).map(i => ({ id: i.id, name: i.name, wowheadUrl: wowheadUrl('achievement', i) })),
                missingMore: Math.max(0, missing.length - MAX_ITEMS_SHOWN),
            });
        }
        for (const zone of (vp.collections[expId]?.quests?.zones || [])) {
            if (!zone.items) continue;
            const missing = zone.items.filter(i => !i.is_completed);
            const completed = zone.items.filter(i => i.is_completed);
            if (missing.length === 0 || completed.length === 0) continue;
            recs.push({
                key: `quest:${expId}:${zone.name}`, name: zone.name, dimension: 'Quetes', color: DIMENSION_COLORS.quests,
                completed: completed.length, total: zone.items.length, missing: missing.length,
                percent: (completed.length / zone.items.length) * 100,
                missingItems: missing.slice(0, MAX_ITEMS_SHOWN).map(i => ({ id: i.id, name: i.name, wowheadUrl: wowheadUrl('quest', i) })),
                missingMore: Math.max(0, missing.length - MAX_ITEMS_SHOWN),
            });
        }
    }

    return recs.sort((a, b) => a.missing - b.missing || b.percent - a.percent).slice(0, 12);
});

const toggleRec = (key) => {
    expandedRec.value = expandedRec.value === key ? null : key;
};

async function poll() {
    try {
        const resp = await axios.get('/api/account/score');
        const data = resp.data;

        if (data.status === 'computing') {
            status.value = 'computing';
            progress.value = data.progress;
            pollTimer = setTimeout(poll, 2500);
        } else if (data.status === 'ready') {
            stopPolling();
            if (data.data) {
                virtualProfile.value = data.data;
                characterCount.value = data.data.characterCount || 0;
                cachedAt.value = data.data.cachedAt || null;
                status.value = 'ready';
            } else {
                status.value = 'ready';
                virtualProfile.value = null;
            }
        }
    } catch (err) {
        stopPolling();
        if (err.response?.status === 401) {
            status.value = 'ready';
            virtualProfile.value = null;
        } else {
            status.value = 'error';
            errorMessage.value = err.response?.data?.message || 'Erreur lors du calcul du score';
        }
    }
}

function startPolling() {
    status.value = 'loading';
    poll();
}

function stopPolling() {
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

async function refresh() {
    try {
        await axios.post('/api/account/score/refresh');
    } catch { /* ignore */ }
    virtualProfile.value = null;
    score.value;
    startPolling();
}

const shareData = computed(() => {
    if (!score.value) return {};
    return {
        variant: 'account',
        characterCount: characterCount.value,
        globalScore: score.value.global,
        rank: rank.value,
        dimensions: score.value.dimensions,
    };
});

onMounted(() => {
    startPolling();
});

onUnmounted(() => {
    stopPolling();
});
</script>
