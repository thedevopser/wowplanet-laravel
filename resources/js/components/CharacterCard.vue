<template>
    <div class="relative overflow-hidden rounded-2xl card-glass border shadow-2xl">
        <div class="absolute inset-0 bg-linear-to-r from-slate-900 via-slate-800/80 to-slate-800/40 z-10"></div>
        <img v-if="character.classIconUrl" :src="character.classIconUrl" class="absolute right-2 sm:right-8 top-1/2 -translate-y-1/2 w-28 h-28 sm:w-48 sm:h-48 object-contain opacity-25" :style="{ filter: `drop-shadow(0 0 20px ${classColor}80) drop-shadow(0 0 40px ${classColor}40)` }" alt="">

        <div class="relative z-20 p-5 sm:p-8 flex flex-col md:flex-row items-start md:items-end gap-4 sm:gap-6">
            <img :src="character.avatarUrl" class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl border-2 shadow-2xl bg-slate-800 object-cover" :style="{ borderColor: classColor + '40' }" alt="">
            <div class="flex-1 mb-2">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black leading-tight mb-2" :style="{ color: classColor }">
                    {{ character.name }}
                </h2>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-sm sm:text-base text-slate-400 font-medium">
                    <span>Niv {{ character.level }}</span>
                    <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                    <span>{{ character.race }}</span>
                    <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                    <span class="font-bold tracking-wide uppercase text-xs sm:text-sm" :style="{ color: classColor }">{{ character.class }}</span>
                    <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                    <span>{{ character.realm }}</span>
                </div>
            </div>

            <div class="flex gap-3 sm:gap-4 mb-2">
                <div class="bg-slate-800/50 backdrop-blur px-3 sm:px-4 py-2 rounded-xl border border-white/5 text-center">
                    <div class="text-[10px] sm:text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Montures</div>
                    <div class="text-lg sm:text-xl font-black text-amber-400">{{ character.mountsCount }}</div>
                </div>
                <div class="bg-slate-800/50 backdrop-blur px-3 sm:px-4 py-2 rounded-xl border border-white/5 text-center">
                    <div class="text-[10px] sm:text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Mascottes</div>
                    <div class="text-lg sm:text-xl font-black text-blue-400">{{ character.petsCount }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { classColors } from '../utils/classColors';

const props = defineProps({
    character: { type: Object, required: true },
});

const classColor = computed(() => classColors[props.character.classId] || '#FFFFFF');
</script>
