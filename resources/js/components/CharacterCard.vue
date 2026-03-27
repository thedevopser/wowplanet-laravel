<template>
    <div class="relative overflow-hidden rounded-2xl card-glass border shadow-2xl">
        <div class="absolute inset-0 bg-linear-to-r from-slate-900 via-slate-800/80 to-slate-800/40 z-10"></div>
        <img v-if="character.classIconUrl" :src="character.classIconUrl" class="absolute right-2 sm:right-8 top-1/2 -translate-y-1/2 w-28 h-28 sm:w-48 sm:h-48 object-contain opacity-25" :style="{ filter: `drop-shadow(0 0 20px ${classColor}80) drop-shadow(0 0 40px ${classColor}40)` }" :alt="`Icône ${character.className}`">

        <div class="relative z-20 p-5 sm:p-8 space-y-4 sm:space-y-6">
            <div class="flex items-start sm:items-end gap-4 sm:gap-6">
                <img :src="character.avatarUrl" class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl border-2 shadow-2xl bg-slate-800 object-cover shrink-0" :style="{ borderColor: classColor + '40' }" :alt="`Avatar de ${character.name}`">
                <div class="min-w-0">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black leading-tight mb-2" :style="{ color: classColor }">
                        {{ character.name }}
                        <span v-if="character.guild" class="text-lg sm:text-xl lg:text-2xl font-bold text-slate-500">&lt;{{ character.guild }}&gt;</span>
                    </h2>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-sm sm:text-base text-slate-400 font-medium">
                        <span>Niv {{ character.level }}</span>
                        <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                        <span>{{ character.race }}</span>
                        <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                        <span class="font-bold" :style="{ color: factionColor }">{{ character.faction }}</span>
                        <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                        <span class="font-bold tracking-wide uppercase text-xs sm:text-sm" :style="{ color: classColor }">{{ character.class }}</span>
                        <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                        <span>{{ character.realm }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 sm:gap-3 items-center">
                <ScoreBadge v-if="score" :score="score.global" />
                <div class="bg-slate-800/50 backdrop-blur px-3 sm:px-4 py-2 rounded-xl border border-white/5 text-center">
                    <div class="text-[10px] sm:text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Montures</div>
                    <div class="text-lg sm:text-xl font-black text-amber-400">{{ character.mountsCount }}</div>
                </div>
                <div class="bg-slate-800/50 backdrop-blur px-3 sm:px-4 py-2 rounded-xl border border-white/5 text-center">
                    <div class="text-[10px] sm:text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Mascottes</div>
                    <div class="text-lg sm:text-xl font-black text-blue-400">{{ character.petsCount }}</div>
                </div>
                <div class="bg-slate-800/50 backdrop-blur px-3 sm:px-4 py-2 rounded-xl border border-white/5 text-center">
                    <div class="text-[10px] sm:text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Décorations</div>
                    <div class="text-lg sm:text-xl font-black text-violet-400">{{ character.decorCount }}</div>
                </div>
                <div class="bg-slate-800/50 backdrop-blur px-3 sm:px-4 py-2 rounded-xl border border-white/5 text-center">
                    <div class="text-[10px] sm:text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Rép. terminées</div>
                    <div class="text-lg sm:text-xl font-black text-purple-400">{{ character.exaltedCount }}</div>
                </div>
                <div class="bg-slate-800/50 backdrop-blur px-3 sm:px-4 py-2 rounded-xl border border-white/5 text-center">
                    <div class="text-[10px] sm:text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Hauts-faits</div>
                    <div class="text-lg sm:text-xl font-black text-amber-400">{{ character.achievementPoints }}</div>
                </div>
                <div v-if="character.mythicKeystone?.rating" class="bg-slate-800/50 backdrop-blur px-3 sm:px-4 py-2 rounded-xl border border-white/5 text-center">
                    <div class="text-[10px] sm:text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">M+ Score</div>
                    <div class="text-lg sm:text-xl font-black" :style="{ color: mythicRatingColor }">{{ Math.round(character.mythicKeystone.rating) }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { classColors } from '../utils/classColors';
import { computeScore } from '../utils/scoreCalculator';
import { useCharacterStore } from '../stores/character';
import ScoreBadge from './ScoreBadge.vue';

const store = useCharacterStore();

const props = defineProps({
    character: { type: Object, required: true },
});

const classColor = computed(() => classColors[props.character.classId] || '#FFFFFF');
const factionColor = computed(() => props.character.faction === 'Alliance' ? '#3b82f6' : '#ef4444');
const score = computed(() => computeScore(props.character));
const mythicRatingColor = computed(() => {
    const c = props.character.mythicKeystone?.rating_color;
    if (!c) return '#f43f5e';
    const { r, g, b } = c;
    if (store.theme === 'light') {
        const brightness = 0.299 * r + 0.587 * g + 0.114 * b;
        if (brightness > 170) {
            const f = 0.45;
            return `rgb(${Math.round(r * f)}, ${Math.round(g * f)}, ${Math.round(b * f)})`;
        }
    }
    return `rgb(${r}, ${g}, ${b})`;
});
</script>
