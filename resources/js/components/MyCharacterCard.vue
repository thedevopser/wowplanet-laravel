<template>
    <div class="relative">
        <Link
            :href="`/character/${character.realmSlug}/${character.name.toLowerCase()}`"
            class="block bg-slate-800/40 border border-white/5 p-5 rounded-2xl hover:bg-slate-800/60 hover:border-blue-500/20 transition-all group text-left"
            :class="isFavorite ? 'border-amber-500/25 bg-amber-500/[0.03]' : ''"
        >
            <div class="flex items-center gap-4">
                <img
                    v-if="character.avatarUrl"
                    :src="character.avatarUrl"
                    :alt="character.name"
                    class="w-12 h-12 rounded-xl border border-white/10 shadow-lg bg-slate-800 object-cover"
                    :style="{ borderColor: classColor + '30' }"
                >
                <div
                    v-else
                    class="w-12 h-12 rounded-xl flex items-center justify-center text-lg font-black border border-white/10 shadow-lg"
                    :style="{ backgroundColor: classColor + '15', color: classColor, borderColor: classColor + '30' }"
                >
                    {{ character.name.charAt(0) }}
                </div>
                <div class="flex-1 min-w-0 pr-7">
                    <div class="text-base md:text-lg font-bold truncate group-hover:text-blue-400 transition-colors" :style="{ color: classColor }">
                        {{ character.name }}
                    </div>
                    <div class="text-xs sm:text-sm text-slate-500 truncate">{{ character.realm }}</div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-3 text-[11px] sm:text-xs text-slate-400">
                <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5 font-mono">Niv {{ character.level }}</span>
                <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5 font-bold" :style="{ color: character.faction === 'Alliance' ? '#3b82f6' : '#ef4444' }">{{ character.faction }}</span>
                <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5">{{ character.raceName }}</span>
                <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5" :style="{ color: classColor }">{{ character.className }}</span>
            </div>
        </Link>

        <button
            type="button"
            :disabled="favoriteDisabled"
            :aria-label="ariaLabel"
            :aria-pressed="isFavorite"
            :title="favoriteDisabled ? `${maxFavorites} favoris maximum` : ariaLabel"
            :class="[
                'absolute top-3 right-3 z-10 p-1.5 rounded-lg transition-all',
                isFavorite
                    ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10'
                    : favoriteDisabled
                        ? 'text-slate-700 cursor-not-allowed'
                        : 'text-slate-600 hover:text-amber-400 hover:bg-white/5',
            ]"
            @click="$emit('toggle-favorite')"
        >
            <svg class="w-4 h-4" :fill="isFavorite ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11c.08.2.27.34.49.36l5.52.44c.5.04.71.67.32 1l-4.2 3.6a.56.56 0 00-.19.57l1.28 5.39c.12.49-.41.88-.84.62l-4.73-2.89a.56.56 0 00-.58 0l-4.73 2.89c-.43.26-.96-.13-.84-.62l1.28-5.39a.56.56 0 00-.19-.57l-4.2-3.6c-.39-.33-.18-.96.32-1l5.52-.44a.56.56 0 00.49-.36L11.48 3.5z" />
            </svg>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { classColors } from '../utils/classColors';
import { MAX_FAVORITES } from '../stores/favorites';

const props = defineProps({
    character: { type: Object, required: true },
    isFavorite: { type: Boolean, default: false },
    favoriteDisabled: { type: Boolean, default: false },
});

defineEmits(['toggle-favorite']);

const maxFavorites = MAX_FAVORITES;

const classColor = computed(() => classColors[props.character.classId] || '#FFFFFF');

const ariaLabel = computed(() => props.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris');
</script>
