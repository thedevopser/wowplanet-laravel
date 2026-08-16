<template>
    <div class="space-y-6">
        <!-- Empty state -->
        <div v-if="!raids || !raids.length" class="card-glass rounded-2xl sm:rounded-3xl border p-8 text-center">
            <p class="text-slate-500 text-sm">Aucune progression de raid pour la saison en cours.</p>
        </div>

        <template v-else>
            <div
                v-for="raid in raids"
                :key="raid.instance_id"
                class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden"
            >
                <div class="absolute top-0 right-0 w-32 h-32 bg-rose-600/5 blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10 space-y-5">
                    <h3 class="text-lg sm:text-2xl font-black text-white">
                        <button
                            type="button"
                            :data-testid="`raid-toggle-${raid.instance_id}`"
                            :aria-expanded="String(!isCollapsed(raid))"
                            :aria-controls="`raid-body-${raid.instance_id}`"
                            class="w-full flex items-center justify-between gap-3 text-left cursor-pointer group"
                            @click="toggle(raid)"
                        >
                            <span class="flex items-center gap-3 min-w-0">
                                <span class="w-2 h-6 sm:h-8 bg-rose-500 rounded-full shadow-lg shadow-rose-500/50 shrink-0"></span>
                                <span class="truncate">{{ raid.instance_name }}</span>
                            </span>

                            <span class="flex items-center gap-3 shrink-0">
                                <span
                                    v-if="isCollapsed(raid)"
                                    :data-testid="`raid-summary-${raid.instance_id}`"
                                    class="hidden sm:flex items-center gap-2 text-xs font-mono font-bold"
                                >
                                    <span
                                        v-for="(entry, index) in summaryFor(raid)"
                                        :key="entry.type"
                                        :class="entry.cleared ? 'text-emerald-400' : 'text-slate-400'"
                                    >
                                        <span v-if="index > 0" class="text-slate-600 mr-2">·</span>
                                        {{ entry.label }} {{ entry.progress }}
                                    </span>
                                </span>

                                <svg
                                    class="w-5 h-5 text-slate-400 transition-transform group-hover:text-slate-200"
                                    :class="isCollapsed(raid) ? '-rotate-90' : ''"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>
                    </h3>

                    <div
                        v-if="!isCollapsed(raid)"
                        :id="`raid-body-${raid.instance_id}`"
                        :data-testid="`raid-body-${raid.instance_id}`"
                        class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 animate-in slide-in-from-top-2 duration-300"
                    >
                        <div
                            v-for="difficulty in DIFFICULTIES"
                            :key="difficulty.type"
                            :data-testid="`difficulty-${difficulty.type}`"
                            class="rounded-2xl border p-4 transition-all"
                            :class="modeFor(raid, difficulty.type)
                                ? 'card-glass border-white/10'
                                : 'border-white/5 opacity-40'"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-bold text-white">{{ difficulty.label }}</span>
                                <span
                                    v-if="modeFor(raid, difficulty.type)"
                                    class="text-sm font-black font-mono"
                                    :class="isCleared(modeFor(raid, difficulty.type)) ? 'text-emerald-400' : 'text-slate-300'"
                                >
                                    {{ modeFor(raid, difficulty.type).completed_count }}/{{ modeFor(raid, difficulty.type).total_count }}
                                </span>
                                <span v-else class="text-xs text-slate-600 font-mono">—</span>
                            </div>

                            <ul v-if="modeFor(raid, difficulty.type)" class="space-y-1">
                                <li
                                    v-for="boss in modeFor(raid, difficulty.type).encounters"
                                    :key="boss.id"
                                    class="flex items-center justify-between gap-2 text-xs text-slate-400 py-1 border-b border-white/5 last:border-0"
                                >
                                    <span class="text-slate-200 truncate">{{ boss.name }}</span>
                                    <span class="text-slate-600 shrink-0">{{ formatDate(boss.last_kill_timestamp) }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useCharacterStore } from '../stores/character';

const store = useCharacterStore();

const raids = computed(() => store.character?.raids ?? null);

// Ordre d'affichage des difficultés (aligné sur l'ordre de l'aggregator backend).
// `short` sert au résumé affiché dans l'en-tête d'un cadre replié.
const DIFFICULTIES = [
    { type: 'LFR', label: 'LFR', short: 'LFR' },
    { type: 'NORMAL', label: 'Normal', short: 'N' },
    { type: 'HEROIC', label: 'Héroïque', short: 'H' },
    { type: 'MYTHIC', label: 'Mythique', short: 'M' },
];

const STORAGE_KEY = 'wowplanet-raids-collapsed';

// instance_id des raids repliés. Volontairement vide au départ : le localStorage
// n'est lu qu'au montage, pour que le rendu SSR et le premier rendu client
// coïncident (sinon Vue signale un hydration mismatch).
const collapsed = ref(new Set());

onMounted(() => {
    if (typeof localStorage === 'undefined') return;

    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
        if (Array.isArray(stored)) {
            collapsed.value = new Set(stored);
        }
    } catch {
        // Entrée corrompue : on repart tout déplié plutôt que de casser l'onglet.
    }
});

function persist() {
    if (typeof localStorage === 'undefined') return;
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...collapsed.value]));
}

function isCollapsed(raid) {
    return collapsed.value.has(raid.instance_id);
}

function toggle(raid) {
    const next = new Set(collapsed.value);
    if (!next.delete(raid.instance_id)) {
        next.add(raid.instance_id);
    }
    collapsed.value = next;
    persist();
}

// Ne résume que les difficultés dont le personnage a une progression : les
// autres n'apprendraient rien et allongeraient l'en-tête pour rien.
function summaryFor(raid) {
    return DIFFICULTIES
        .map(difficulty => ({ difficulty, mode: modeFor(raid, difficulty.type) }))
        .filter(({ mode }) => mode && mode.total_count > 0)
        .map(({ difficulty, mode }) => ({
            type: difficulty.type,
            label: difficulty.short,
            progress: `${mode.completed_count}/${mode.total_count}`,
            cleared: isCleared(mode),
        }));
}

function modeFor(raid, difficultyType) {
    return raid.modes.find(mode => mode.difficulty_type === difficultyType) ?? null;
}

function isCleared(mode) {
    return mode.total_count > 0 && mode.completed_count >= mode.total_count;
}

function formatDate(timestampMs) {
    if (!timestampMs) return '';
    return new Date(timestampMs).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
