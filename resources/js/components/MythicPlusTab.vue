<template>
    <div class="space-y-6">
        <!-- Empty state -->
        <div v-if="!mythicData" class="card-glass rounded-2xl sm:rounded-3xl border p-8 text-center">
            <p class="text-slate-500 text-sm">Aucune donn&eacute;e Mythique+ pour la saison en cours.</p>
        </div>

        <template v-else>
            <!-- Rating Header -->
            <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-rose-600/5 blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10">
                    <div class="flex justify-between items-end mb-2">
                        <div>
                            <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                                <div class="w-2 h-6 sm:h-8 bg-rose-500 rounded-full shadow-lg shadow-rose-500/50"></div>
                                Score Mythique+
                            </h3>
                            <p class="text-slate-500 text-xs sm:text-sm mt-1">Saison {{ mythicData.season_id }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl sm:text-4xl font-black font-mono" :style="{ color: ratingColorCss }">
                                {{ Math.round(mythicData.rating) }}
                            </div>
                            <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">
                                {{ uniqueDungeonCount }} donjon{{ uniqueDungeonCount > 1 ? 's' : '' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No runs state -->
            <div v-if="!mythicData.best_runs.length" class="card-glass rounded-2xl sm:rounded-3xl border p-8 text-center">
                <p class="text-slate-500 text-sm">Aucune course enregistr&eacute;e cette saison.</p>
            </div>

            <!-- Two-column layout: Timed / Untimed -->
            <div v-else class="flex flex-col lg:flex-row gap-6">
                <!-- Timed column -->
                <div data-testid="timed-column" class="flex-1 space-y-4">
                    <h4 class="text-sm sm:text-base font-bold text-white flex items-center gap-2">
                        <div class="w-2 h-5 bg-emerald-500 rounded-full"></div>
                        Dans les temps
                        <span class="text-slate-500 text-xs font-mono">({{ timedRuns.length }})</span>
                    </h4>

                    <p v-if="!timedRuns.length" class="text-slate-500 text-sm">Aucune course dans les temps.</p>

                    <div
                        v-for="run in timedRuns"
                        :key="run.dungeon_id"
                        class="card-glass rounded-2xl border p-4 sm:p-6 relative overflow-hidden group hover:border-white/10 transition-all"
                    >
                        <div class="absolute top-0 right-0 w-20 h-20 bg-emerald-600/10 blur-2xl -mr-8 -mt-8"></div>
                        <div class="relative z-10 space-y-3">
                            <div class="flex justify-between items-start gap-2">
                                <div class="min-w-0">
                                    <h4 class="text-sm sm:text-base font-bold text-white truncate">{{ run.dungeon_name }}</h4>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[10px] sm:text-xs text-slate-500">{{ formatDate(run.completed_at) }}</span>
                                    </div>
                                </div>
                                <span class="px-2 py-1 rounded-lg text-xs sm:text-sm font-black bg-slate-700/80 border border-white/10 text-white shrink-0">
                                    +{{ run.level }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] text-slate-500 uppercase font-bold">Score</div>
                                    <div class="text-sm sm:text-base font-black font-mono" :style="{ color: toColorCss(run.map_score_color) }">
                                        {{ Math.round(run.map_score) }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] text-slate-500 uppercase font-bold">Dur&eacute;e</div>
                                    <div class="text-sm sm:text-base font-mono text-slate-300">{{ formatDuration(run.duration_ms) }}</div>
                                </div>
                            </div>

                            <details class="group/details">
                                <summary class="cursor-pointer text-[10px] sm:text-xs text-slate-500 hover:text-slate-300 transition-colors uppercase font-bold tracking-wider">
                                    Composition du groupe
                                </summary>
                                <div class="mt-2 space-y-1">
                                    <div
                                        v-for="(member, idx) in run.members"
                                        :key="idx"
                                        class="flex items-center justify-between text-xs text-slate-400 py-1 border-b border-white/5 last:border-0"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="text-white font-medium truncate">{{ member.name }}</span>
                                            <span class="text-slate-600 hidden sm:inline">{{ member.realm }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="text-slate-500">{{ member.spec }}</span>
                                            <span class="font-mono text-slate-600">{{ member.ilvl }}</span>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>

                <!-- Separator -->
                <div class="hidden lg:block w-px bg-slate-700/50 self-stretch"></div>
                <div class="lg:hidden h-px bg-slate-700/50"></div>

                <!-- Untimed column -->
                <div data-testid="untimed-column" class="flex-1 space-y-4">
                    <h4 class="text-sm sm:text-base font-bold text-white flex items-center gap-2">
                        <div class="w-2 h-5 bg-orange-500 rounded-full"></div>
                        Hors temps
                        <span class="text-slate-500 text-xs font-mono">({{ untimedRuns.length }})</span>
                    </h4>

                    <p v-if="!untimedRuns.length" class="text-slate-500 text-sm">Aucune course hors temps.</p>

                    <div
                        v-for="run in untimedRuns"
                        :key="run.dungeon_id"
                        class="card-glass rounded-2xl border p-4 sm:p-6 relative overflow-hidden group hover:border-white/10 transition-all"
                    >
                        <div class="absolute top-0 right-0 w-20 h-20 bg-orange-600/10 blur-2xl -mr-8 -mt-8"></div>
                        <div class="relative z-10 space-y-3">
                            <div class="flex justify-between items-start gap-2">
                                <div class="min-w-0">
                                    <h4 class="text-sm sm:text-base font-bold text-white truncate">{{ run.dungeon_name }}</h4>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[10px] sm:text-xs text-slate-500">{{ formatDate(run.completed_at) }}</span>
                                    </div>
                                </div>
                                <span class="px-2 py-1 rounded-lg text-xs sm:text-sm font-black bg-slate-700/80 border border-white/10 text-white shrink-0">
                                    +{{ run.level }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] text-slate-500 uppercase font-bold">Score</div>
                                    <div class="text-sm sm:text-base font-black font-mono" :style="{ color: toColorCss(run.map_score_color) }">
                                        {{ Math.round(run.map_score) }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] text-slate-500 uppercase font-bold">Dur&eacute;e</div>
                                    <div class="text-sm sm:text-base font-mono text-slate-300">{{ formatDuration(run.duration_ms) }}</div>
                                </div>
                            </div>

                            <details class="group/details">
                                <summary class="cursor-pointer text-[10px] sm:text-xs text-slate-500 hover:text-slate-300 transition-colors uppercase font-bold tracking-wider">
                                    Composition du groupe
                                </summary>
                                <div class="mt-2 space-y-1">
                                    <div
                                        v-for="(member, idx) in run.members"
                                        :key="idx"
                                        class="flex items-center justify-between text-xs text-slate-400 py-1 border-b border-white/5 last:border-0"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="text-white font-medium truncate">{{ member.name }}</span>
                                            <span class="text-slate-600 hidden sm:inline">{{ member.realm }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="text-slate-500">{{ member.spec }}</span>
                                            <span class="font-mono text-slate-600">{{ member.ilvl }}</span>
                                        </div>
                                    </div>
                                </div>
                            </details>
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

const mythicData = computed(() => store.character?.mythicKeystone ?? null);

const ratingColorCss = computed(() => toColorCss(mythicData.value?.rating_color));

function deduplicateRuns(runs) {
    const map = new Map();
    for (const run of runs) {
        const existing = map.get(run.dungeon_id);
        if (!existing || run.level > existing.level) {
            map.set(run.dungeon_id, run);
        }
    }
    return [...map.values()];
}

const timedRuns = computed(() => {
    if (!mythicData.value?.best_runs) return [];
    return deduplicateRuns(mythicData.value.best_runs.filter(r => r.is_timed));
});

const untimedRuns = computed(() => {
    if (!mythicData.value?.best_runs) return [];
    return deduplicateRuns(mythicData.value.best_runs.filter(r => !r.is_timed));
});

const uniqueDungeonCount = computed(() => {
    if (!mythicData.value?.best_runs) return 0;
    return new Set(mythicData.value.best_runs.map(r => r.dungeon_id)).size;
});

function toColorCss(color) {
    if (!color) return '#e2e8f0';
    const { r, g, b } = color;
    if (store.theme === 'light') {
        const brightness = 0.299 * r + 0.587 * g + 0.114 * b;
        if (brightness > 170) {
            const f = 0.45;
            return `rgb(${Math.round(r * f)}, ${Math.round(g * f)}, ${Math.round(b * f)})`;
        }
    }
    return `rgb(${r}, ${g}, ${b})`;
}

function formatDuration(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

function formatDate(timestampMs) {
    if (!timestampMs) return '';
    return new Date(timestampMs).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
