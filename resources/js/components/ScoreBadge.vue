<template>
    <div class="relative w-16 h-16 sm:w-20 sm:h-20 shrink-0">
        <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
            <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="6" class="text-slate-800" />
            <circle
                cx="50" cy="50" r="42"
                fill="none"
                :stroke="color"
                stroke-width="6"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="offset"
                class="transition-all duration-1000 ease-out"
            />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-sm sm:text-lg font-black tabular-nums" :style="{ color }">{{ Math.round(score) }}</span>
            <span class="text-[8px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider">Score</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { getScoreColor } from '../utils/scoreCalculator';

const props = defineProps({
    score: { type: Number, default: 0 },
});

const circumference = 2 * Math.PI * 42;
const offset = computed(() => circumference - (props.score / 100) * circumference);
const color = computed(() => getScoreColor(props.score));
</script>
