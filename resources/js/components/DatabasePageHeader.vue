<template>
    <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 blur-3xl -mr-16 -mt-16" :class="glowClass"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-end">
                <div>
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                        <div class="w-2 h-6 sm:h-8 rounded-full shadow-lg" :class="barClass"></div>
                        {{ title }}
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm md:text-base mt-1">{{ subtitle }}</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl sm:text-3xl font-black font-mono" :class="countClass">
                        {{ count.toLocaleString('fr-FR') }}
                    </div>
                    <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">{{ countLabel }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    count: { type: Number, default: 0 },
    countLabel: { type: String, default: '' },
    accentColor: { type: String, default: 'blue' },
});

const colorMap = {
    amber: { glow: 'bg-amber-600/5', bar: 'bg-amber-500 shadow-amber-500/50', count: 'text-amber-400' },
    blue: { glow: 'bg-blue-600/5', bar: 'bg-blue-500 shadow-blue-500/50', count: 'text-blue-400' },
    violet: { glow: 'bg-violet-600/5', bar: 'bg-violet-500 shadow-violet-500/50', count: 'text-violet-400' },
    emerald: { glow: 'bg-emerald-600/5', bar: 'bg-emerald-500 shadow-emerald-500/50', count: 'text-emerald-400' },
};

const colors = computed(() => colorMap[props.accentColor] || colorMap.blue);
const glowClass = computed(() => colors.value.glow);
const barClass = computed(() => colors.value.bar);
const countClass = computed(() => colors.value.count);
</script>
