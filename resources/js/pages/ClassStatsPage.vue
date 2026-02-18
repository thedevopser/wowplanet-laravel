<template>
    <div class="space-y-8 animate-in fade-in duration-500">
        <div class="text-center max-w-3xl mx-auto">
            <h2 class="text-3xl md:text-4xl font-black mb-3">
                <span class="bg-clip-text text-transparent bg-linear-to-r from-blue-200 via-blue-400 to-blue-600">Mes classes</span>
            </h2>
            <p class="text-slate-400 text-sm md:text-base">R&eacute;partition de vos <span class="text-white font-bold font-mono">{{ totalCharacters }}</span> personnages par classe</p>
        </div>

        <!-- Loading -->
        <LoadingSpinner
            v-if="store.loadingCharacters"
            title="Chargement en cours..."
            subtitle="Analyse de vos personnages"
        />

        <template v-else-if="classStats.length">
            <!-- Podium -->
            <div class="flex items-end justify-center gap-2 sm:gap-4 md:gap-6 max-w-3xl mx-auto pt-8">
                <!-- 2nd place -->
                <div v-if="podiumClasses[1]" class="flex-1 min-w-0 max-w-[200px] sm:max-w-[220px]">
                    <div class="card-glass rounded-2xl border p-3 sm:p-6 text-center relative overflow-hidden border-slate-400/30 shadow-lg">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-linear-to-r from-slate-400 to-slate-300"></div>
                        <div class="w-8 h-8 sm:w-10 sm:h-10 mx-auto rounded-full mb-3 bg-linear-to-br from-slate-200 via-slate-300 to-slate-400 shadow-lg flex items-center justify-center">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-linear-to-br from-slate-300 via-white to-slate-400 border border-slate-200/50"></div>
                        </div>
                        <img
                            v-if="podiumClasses[1].iconUrl"
                            :src="podiumClasses[1].iconUrl"
                            :alt="podiumClasses[1].className"
                            class="w-14 h-14 sm:w-16 sm:h-16 mx-auto rounded-2xl border border-white/10 mb-3 shadow-lg object-cover"
                            :style="{ borderColor: podiumClasses[1].color + '30' }"
                        >
                        <div
                            v-else
                            class="w-14 h-14 sm:w-16 sm:h-16 mx-auto rounded-2xl flex items-center justify-center text-xl sm:text-2xl font-black border border-white/10 mb-3 shadow-lg"
                            :style="{ backgroundColor: podiumClasses[1].color + '15', color: podiumClasses[1].color, borderColor: podiumClasses[1].color + '30' }"
                        >
                            {{ podiumClasses[1].className.charAt(0) }}
                        </div>
                        <div class="text-xs sm:text-sm font-bold mb-1 truncate" :style="{ color: podiumClasses[1].color }">{{ podiumClasses[1].className }}</div>
                        <div class="text-xl sm:text-2xl font-black font-mono text-white">{{ podiumClasses[1].count }}</div>
                        <div class="text-[9px] sm:text-[10px] text-slate-500 uppercase font-bold tracking-wider">personnage{{ podiumClasses[1].count > 1 ? 's' : '' }}</div>
                    </div>
                </div>

                <!-- 1st place -->
                <div v-if="podiumClasses[0]" class="flex-1 min-w-0 max-w-[220px] sm:max-w-[260px] -mt-8">
                    <div class="card-glass rounded-2xl border p-5 sm:p-8 text-center relative overflow-hidden border-amber-400/40 shadow-2xl shadow-amber-500/10">
                        <div class="absolute top-0 left-0 right-0 h-1.5 bg-linear-to-r from-amber-500 via-yellow-400 to-amber-500"></div>
                        <div class="absolute top-0 right-0 w-32 h-32 bg-amber-500/5 blur-3xl -mr-16 -mt-16"></div>
                        <div class="relative z-10">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 mx-auto rounded-full mb-4 bg-linear-to-br from-amber-300 via-yellow-400 to-amber-500 shadow-lg shadow-amber-500/30 flex items-center justify-center">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-linear-to-br from-yellow-300 via-amber-200 to-yellow-500 border border-amber-300/50"></div>
                            </div>
                            <img
                                v-if="podiumClasses[0].iconUrl"
                                :src="podiumClasses[0].iconUrl"
                                :alt="podiumClasses[0].className"
                                class="w-16 h-16 sm:w-20 sm:h-20 mx-auto rounded-2xl border-2 mb-4 shadow-2xl object-cover"
                                :style="{ borderColor: podiumClasses[0].color + '40' }"
                            >
                            <div
                                v-else
                                class="w-16 h-16 sm:w-20 sm:h-20 mx-auto rounded-2xl flex items-center justify-center text-2xl sm:text-3xl font-black border-2 mb-4 shadow-2xl"
                                :style="{ backgroundColor: podiumClasses[0].color + '15', color: podiumClasses[0].color, borderColor: podiumClasses[0].color + '40' }"
                            >
                                {{ podiumClasses[0].className.charAt(0) }}
                            </div>
                            <div class="text-sm sm:text-base font-bold mb-2 truncate" :style="{ color: podiumClasses[0].color }">{{ podiumClasses[0].className }}</div>
                            <div class="text-3xl sm:text-4xl font-black font-mono text-white">{{ podiumClasses[0].count }}</div>
                            <div class="text-[9px] sm:text-[10px] text-slate-500 uppercase font-bold tracking-wider">personnage{{ podiumClasses[0].count > 1 ? 's' : '' }}</div>
                        </div>
                    </div>
                </div>

                <!-- 3rd place -->
                <div v-if="podiumClasses[2]" class="flex-1 min-w-0 max-w-[200px] sm:max-w-[220px]">
                    <div class="card-glass rounded-2xl border p-3 sm:p-6 text-center relative overflow-hidden border-amber-700/30 shadow-lg">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-linear-to-r from-amber-800 to-amber-600"></div>
                        <div class="w-8 h-8 sm:w-10 sm:h-10 mx-auto rounded-full mb-3 bg-linear-to-br from-amber-600 via-amber-700 to-amber-800 shadow-lg flex items-center justify-center">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-linear-to-br from-amber-500 via-amber-600 to-amber-800 border border-amber-600/50"></div>
                        </div>
                        <img
                            v-if="podiumClasses[2].iconUrl"
                            :src="podiumClasses[2].iconUrl"
                            :alt="podiumClasses[2].className"
                            class="w-14 h-14 sm:w-16 sm:h-16 mx-auto rounded-2xl border border-white/10 mb-3 shadow-lg object-cover"
                            :style="{ borderColor: podiumClasses[2].color + '30' }"
                        >
                        <div
                            v-else
                            class="w-14 h-14 sm:w-16 sm:h-16 mx-auto rounded-2xl flex items-center justify-center text-xl sm:text-2xl font-black border border-white/10 mb-3 shadow-lg"
                            :style="{ backgroundColor: podiumClasses[2].color + '15', color: podiumClasses[2].color, borderColor: podiumClasses[2].color + '30' }"
                        >
                            {{ podiumClasses[2].className.charAt(0) }}
                        </div>
                        <div class="text-xs sm:text-sm font-bold mb-1 truncate" :style="{ color: podiumClasses[2].color }">{{ podiumClasses[2].className }}</div>
                        <div class="text-xl sm:text-2xl font-black font-mono text-white">{{ podiumClasses[2].count }}</div>
                        <div class="text-[9px] sm:text-[10px] text-slate-500 uppercase font-bold tracking-wider">personnage{{ podiumClasses[2].count > 1 ? 's' : '' }}</div>
                    </div>
                </div>
            </div>

            <!-- Other classes -->
            <div v-if="otherClasses.length" class="space-y-4">
                <h4 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4">
                    Autres classes
                    <div class="flex-1 h-px bg-slate-700"></div>
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div
                        v-for="(cls, index) in otherClasses"
                        :key="cls.classId"
                        class="bg-slate-800/40 border border-white/5 rounded-2xl p-4 flex items-center gap-3 hover:bg-slate-800/60 transition-colors"
                    >
                        <img
                            v-if="cls.iconUrl"
                            :src="cls.iconUrl"
                            :alt="cls.className"
                            class="w-10 h-10 rounded-xl border border-white/10 shrink-0 object-cover"
                            :style="{ borderColor: cls.color + '30' }"
                        >
                        <div
                            v-else
                            class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-black border border-white/10 shrink-0"
                            :style="{ backgroundColor: cls.color + '15', color: cls.color, borderColor: cls.color + '30' }"
                        >
                            {{ cls.className.charAt(0) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs sm:text-sm font-bold truncate" :style="{ color: cls.color }">{{ cls.className }}</div>
                            <div class="text-lg font-black font-mono text-white leading-tight">{{ cls.count }}</div>
                        </div>
                        <div class="text-[10px] font-mono text-slate-600 shrink-0">#{{ index + 4 }}</div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Empty state -->
        <div v-else class="text-center py-16">
            <p class="text-slate-500">Aucun personnage trouv&eacute;.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useCharacterStore } from '../stores/character';
import { classColors } from '../utils/classColors';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const store = useCharacterStore();

onMounted(() => {
    store.fetchClassIcons();
    if (!store.userCharacters.length) {
        store.fetchUserCharacters();
    }
});

const classStats = computed(() => {
    const map = {};
    store.userCharacters.forEach(c => {
        if (!map[c.classId]) {
            map[c.classId] = {
                classId: c.classId,
                className: c.className,
                count: 0,
                color: classColors[c.classId] || '#FFFFFF',
                iconUrl: store.classIcons[c.classId] || '',
            };
        }
        map[c.classId].count++;
    });
    return Object.values(map).sort((a, b) => b.count - a.count);
});

const podiumClasses = computed(() => classStats.value.slice(0, 3));
const otherClasses = computed(() => classStats.value.slice(3));
const totalCharacters = computed(() => store.userCharacters.length);
</script>
