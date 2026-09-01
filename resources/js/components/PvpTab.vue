<template>
    <div class="space-y-6">
        <!-- Chargement -->
        <div v-if="loading" data-testid="pvp-loading" class="flex justify-center py-12">
            <div class="animate-spin w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full"></div>
        </div>

        <!-- Erreur -->
        <div v-else-if="error" class="card-glass rounded-2xl sm:rounded-3xl border p-8 text-center">
            <p class="text-slate-400 text-sm">Impossible de charger les données PvP.</p>
        </div>

        <!-- Aucun PvP -->
        <div v-else-if="!pvp" class="card-glass rounded-2xl sm:rounded-3xl border p-8 text-center">
            <p class="text-slate-500 text-sm">Aucune donnée PvP pour ce personnage.</p>
        </div>

        <template v-else>
            <!-- En-tête de saison -->
            <div data-testid="pvp-header" class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-amber-600/5 blur-3xl -mr-16 -mt-16"></div>
                <div class="relative z-10 flex flex-wrap justify-between items-end gap-4">
                    <div>
                        <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                            <div class="w-2 h-6 sm:h-8 bg-amber-500 rounded-full shadow-lg shadow-amber-500/50"></div>
                            Joueur contre joueur
                        </h3>
                        <p v-if="pvp.season_id" class="text-slate-500 text-xs sm:text-sm mt-1">Saison {{ pvp.season_id }}</p>
                    </div>

                    <div class="flex items-end gap-6">
                        <div v-if="pvp.best_rating" class="text-right">
                            <div class="text-3xl sm:text-4xl font-black font-mono text-amber-400">{{ pvp.best_rating }}</div>
                            <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">Meilleur cote</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xl sm:text-2xl font-black font-mono text-slate-200">{{ pvp.honor_level }}</div>
                            <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">Niveau d'honneur</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Champs de bataille non cotés -->
            <div
                v-if="pvp.battlegrounds && pvp.battlegrounds.played > 0"
                data-testid="pvp-battlegrounds"
                class="card-glass rounded-2xl sm:rounded-3xl border p-5 flex flex-wrap items-center justify-between gap-4"
            >
                <div class="flex items-center gap-3">
                    <div class="w-2 h-5 bg-slate-500 rounded-full"></div>
                    <span class="text-sm font-bold text-white">Champs de bataille non cotés</span>
                </div>
                <div class="flex items-center gap-5 text-xs font-mono">
                    <span class="text-slate-400">{{ pvp.battlegrounds.played }} joués</span>
                    <span class="text-emerald-400">{{ pvp.battlegrounds.won }} V</span>
                    <span class="text-rose-400">{{ pvp.battlegrounds.lost }} D</span>
                    <span class="text-slate-300 font-bold">{{ pvp.battlegrounds.win_rate }} %</span>
                </div>
            </div>

            <!-- Un cadre par mode de jeu -->
            <div
                v-for="group in pvp.groups"
                :key="group.key"
                :data-testid="`pvp-group-${group.key}`"
                class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 space-y-5"
            >
                <h4 class="text-lg sm:text-xl font-black text-white flex items-center gap-3">
                    <div class="w-2 h-6 bg-amber-500 rounded-full"></div>
                    {{ group.label }}
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div
                        v-for="bracket in group.brackets"
                        :key="bracket.slug"
                        :data-testid="`pvp-bracket-${bracket.slug}`"
                        class="rounded-2xl border border-white/10 bg-slate-900/40 p-4 space-y-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-white truncate">{{ bracket.label }}</div>
                                <div v-if="bracket.tier_name" class="flex items-center gap-1.5 mt-1">
                                    <img
                                        v-if="bracket.tier_icon_url"
                                        :src="bracket.tier_icon_url"
                                        :alt="bracket.tier_name"
                                        class="w-4 h-4 rounded"
                                        loading="lazy"
                                    />
                                    <span class="text-[11px] text-slate-400 truncate">{{ bracket.tier_name }}</span>
                                </div>
                            </div>
                            <div class="text-2xl font-black font-mono text-amber-400 shrink-0">{{ bracket.rating }}</div>
                        </div>

                        <div class="flex items-center justify-between text-xs font-mono border-t border-white/5 pt-3">
                            <span class="text-emerald-400">{{ bracket.won }} V</span>
                            <span class="text-rose-400">{{ bracket.lost }} D</span>
                            <span :class="bracket.win_rate >= 50 ? 'text-emerald-300 font-bold' : 'text-slate-400'">
                                {{ bracket.win_rate }} %
                            </span>
                        </div>

                        <div v-if="bracket.weekly && bracket.weekly.played > 0" class="text-[11px] text-slate-500 font-mono">
                            Cette semaine : {{ bracket.weekly.played }} joués — {{ bracket.weekly.won }} V / {{ bracket.weekly.lost }} D
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    realm: { type: String, required: true },
    name: { type: String, required: true },
});

const loading = ref(true);
const error = ref(false);
const pvp = ref(null);

// Chargement paresseux : l'onglet n'est monté qu'à son ouverture, et le PvP ne
// coûte donc rien aux profils qui n'en font pas (la majorité).
onMounted(async () => {
    try {
        const response = await axios.get(
            `/api/character/${encodeURIComponent(props.realm)}/${encodeURIComponent(props.name)}/pvp`,
        );
        pvp.value = response.data?.pvp ?? null;
    } catch {
        error.value = true;
    } finally {
        loading.value = false;
    }
});
</script>
