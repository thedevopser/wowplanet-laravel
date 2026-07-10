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
                    <h3 class="text-lg sm:text-2xl font-black text-white flex items-center gap-3">
                        <div class="w-2 h-6 sm:h-8 bg-rose-500 rounded-full shadow-lg shadow-rose-500/50"></div>
                        {{ raid.instance_name }}
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
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
import { computed } from 'vue';
import { useCharacterStore } from '../stores/character';

const store = useCharacterStore();

const raids = computed(() => store.character?.raids ?? null);

// Ordre d'affichage des difficultés (aligné sur l'ordre de l'aggregator backend).
const DIFFICULTIES = [
    { type: 'LFR', label: 'LFR' },
    { type: 'NORMAL', label: 'Normal' },
    { type: 'HEROIC', label: 'Héroïque' },
    { type: 'MYTHIC', label: 'Mythique' },
];

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
