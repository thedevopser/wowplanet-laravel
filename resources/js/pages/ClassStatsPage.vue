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
                    <div
                        @click="toggleClass(podiumClasses[1].classId)"
                        class="card-glass rounded-2xl border p-3 sm:p-6 text-center relative overflow-hidden shadow-lg cursor-pointer transition-all duration-200"
                        :class="selectedClassId === podiumClasses[1].classId
                            ? 'border-blue-400/50 ring-2 ring-blue-400/30 scale-[1.02]'
                            : 'border-slate-400/30 hover:scale-[1.01]'"
                    >
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
                    <div
                        @click="toggleClass(podiumClasses[0].classId)"
                        class="card-glass rounded-2xl border p-5 sm:p-8 text-center relative overflow-hidden shadow-2xl shadow-amber-500/10 cursor-pointer transition-all duration-200"
                        :class="selectedClassId === podiumClasses[0].classId
                            ? 'border-blue-400/50 ring-2 ring-blue-400/30 scale-[1.02]'
                            : 'border-amber-400/40 hover:scale-[1.01]'"
                    >
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
                    <div
                        @click="toggleClass(podiumClasses[2].classId)"
                        class="card-glass rounded-2xl border p-3 sm:p-6 text-center relative overflow-hidden shadow-lg cursor-pointer transition-all duration-200"
                        :class="selectedClassId === podiumClasses[2].classId
                            ? 'border-blue-400/50 ring-2 ring-blue-400/30 scale-[1.02]'
                            : 'border-amber-700/30 hover:scale-[1.01]'"
                    >
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
                        @click="toggleClass(cls.classId)"
                        class="rounded-2xl p-4 flex items-center gap-3 cursor-pointer transition-all duration-200"
                        :class="selectedClassId === cls.classId
                            ? 'bg-slate-800/70 border-2 border-blue-400/40 ring-1 ring-blue-400/20'
                            : 'bg-slate-800/40 border border-white/5 hover:bg-slate-800/60'"
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

            <!-- Character Detail Panel -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                leave-active-class="transition-all duration-200 ease-in"
                enter-from-class="opacity-0 -translate-y-4"
                enter-to-class="opacity-100 translate-y-0"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-4"
            >
                <div v-if="selectedClassInfo" id="class-detail-panel" class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <h4 class="text-sm font-black uppercase tracking-[0.2em] flex items-center gap-3 min-w-0">
                            <span :style="{ color: selectedClassInfo.color }">{{ selectedClassInfo.className }}</span>
                            <span class="text-slate-500">&mdash; {{ selectedClassCharacters.length }} personnage{{ selectedClassCharacters.length > 1 ? 's' : '' }}</span>
                            <div class="flex-1 h-px bg-slate-700"></div>
                        </h4>
                        <button
                            @click="selectedClassId = null"
                            class="text-slate-500 hover:text-slate-300 transition-colors p-1 rounded-lg hover:bg-slate-800/50 shrink-0"
                            title="Fermer"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        <router-link
                            v-for="char in selectedClassCharacters"
                            :key="char.name + '-' + char.realmSlug"
                            :to="`/character/${char.realmSlug}/${char.name.toLowerCase()}`"
                            class="bg-slate-800/40 border border-white/5 p-4 rounded-2xl hover:bg-slate-800/60 hover:border-blue-500/20 transition-all group text-left"
                        >
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="char.avatarUrl"
                                    :src="char.avatarUrl"
                                    :alt="char.name"
                                    class="w-10 h-10 rounded-xl border border-white/10 shadow-lg bg-slate-800 object-cover"
                                    :style="{ borderColor: selectedClassInfo.color + '30' }"
                                >
                                <div
                                    v-else
                                    class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-black border border-white/10 shadow-lg"
                                    :style="{ backgroundColor: selectedClassInfo.color + '15', color: selectedClassInfo.color, borderColor: selectedClassInfo.color + '30' }"
                                >
                                    {{ char.name.charAt(0) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-bold truncate group-hover:text-blue-400 transition-colors" :style="{ color: selectedClassInfo.color }">{{ char.name }}</div>
                                    <div class="text-xs text-slate-500 truncate">{{ char.realm }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 mt-2 text-[11px] text-slate-400">
                                <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5 font-mono">Niv {{ char.level }}</span>
                                <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5">{{ char.raceName }}</span>
                            </div>
                        </router-link>
                    </div>
                </div>
            </Transition>
        </template>

        <!-- Empty state -->
        <div v-else class="text-center py-16 max-w-md mx-auto">
            <div class="w-16 h-16 mx-auto mb-6 bg-slate-800/60 border border-white/10 rounded-2xl flex items-center justify-center">
                <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-300 mb-2">Aucun personnage trouvé</h3>
            <p class="text-sm text-slate-500 mb-6">Connectez-vous avec Battle.net pour voir la répartition de vos personnages par classe.</p>
            <a
                href="/auth/blizzard/redirect"
                class="btn-gradient text-white font-semibold px-6 py-2.5 rounded-lg text-sm shadow-lg shadow-blue-500/20 inline-block"
            >
                Se connecter avec Battle.net
            </a>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, nextTick } from 'vue';
import { useCharacterStore } from '../stores/character';
import { classColors } from '../utils/classColors';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const store = useCharacterStore();
const selectedClassId = ref(null);

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

const selectedClassCharacters = computed(() => {
    if (!selectedClassId.value) return [];
    return store.userCharacters
        .filter(c => c.classId === selectedClassId.value)
        .sort((a, b) => b.level - a.level);
});

const selectedClassInfo = computed(() => {
    if (!selectedClassId.value) return null;
    return classStats.value.find(cls => cls.classId === selectedClassId.value) || null;
});

function toggleClass(classId) {
    if (selectedClassId.value === classId) {
        selectedClassId.value = null;
        return;
    }

    selectedClassId.value = classId;
    nextTick(() => {
        const panel = document.getElementById('class-detail-panel');
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
}
</script>
