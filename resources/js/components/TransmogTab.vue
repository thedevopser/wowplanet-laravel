<template>
    <div class="space-y-6 py-4">
        <!-- Complétion globale -->
        <div class="rounded-xl border border-white/10 bg-slate-800/40 p-5">
            <div class="flex items-baseline justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Garde-robe</h3>
                <span class="text-lg font-mono font-bold text-violet-400">{{ globalPercent }}%</span>
            </div>
            <div class="h-3 rounded-full bg-slate-900/60 overflow-hidden">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-violet-600 to-violet-400 transition-all"
                    :style="{ width: globalPercent + '%' }"
                ></div>
            </div>
            <p class="mt-2 text-xs text-slate-400">
                {{ totalCompleted }} / {{ totalTotal }} apparences débloquées
            </p>
        </div>

        <!-- Filtre catégories -->
        <div v-if="categories.length > 1" class="flex flex-wrap gap-2">
            <button
                v-for="cat in categoryFilters"
                :key="cat.value"
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                :class="activeCategory === cat.value
                    ? 'bg-violet-500/15 border-violet-500/40 text-violet-300'
                    : 'border-white/10 text-slate-400 hover:text-slate-200'"
                @click="activeCategory = cat.value"
            >
                {{ cat.label }}
            </button>
        </div>

        <!-- Grille par slot -->
        <div v-if="visibleSlots.length" class="grid gap-3 sm:grid-cols-2">
            <div
                v-for="slot in visibleSlots"
                :key="slot.slot"
                class="rounded-lg border border-white/10 bg-slate-800/30 p-4"
            >
                <div class="flex items-baseline justify-between mb-2">
                    <span class="text-sm font-semibold text-slate-200">{{ slotLabel(slot.slot) }}</span>
                    <span class="text-xs font-mono text-slate-400">{{ slot.completed }} / {{ slot.total }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-900/60 overflow-hidden">
                    <div
                        class="h-full rounded-full bg-violet-500 transition-all"
                        :style="{ width: slotPercent(slot) + '%' }"
                    ></div>
                </div>
            </div>
        </div>

        <div v-else class="text-center py-10 text-slate-500 text-sm">
            Aucune apparence à afficher.
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    character: { type: Object, required: true },
});

const SLOT_FR = {
    HEAD: 'Tête',
    SHOULDER: 'Épaules',
    SHIRT: 'Chemise',
    CHEST: 'Torse',
    WAIST: 'Ceinture',
    LEGS: 'Jambes',
    FEET: 'Pieds',
    WRIST: 'Poignets',
    HAND: 'Mains',
    CLOAK: 'Cape',
    TABARD: 'Tabard',
    WEAPON: 'Arme',
    SHIELD: 'Bouclier',
    RANGED: 'Distance',
    TWOHWEAPON: 'Arme à deux mains',
    WEAPONOFFHAND: 'Arme en main gauche',
    HOLDABLE: 'Tenu en main gauche',
};

const activeCategory = ref('all');

const slots = computed(() => props.character?.appearances || []);

const totalCompleted = computed(() => slots.value.reduce((sum, s) => sum + (s.completed || 0), 0));
const totalTotal = computed(() => slots.value.reduce((sum, s) => sum + (s.total || 0), 0));
const globalPercent = computed(() => totalTotal.value === 0 ? 0 : Math.round((totalCompleted.value / totalTotal.value) * 100));

const categories = computed(() => [...new Set(slots.value.map(s => s.category).filter(Boolean))]);
const categoryFilters = computed(() => [
    { value: 'all', label: 'Tout' },
    ...categories.value.map(c => ({ value: c, label: c })),
]);

const visibleSlots = computed(() => {
    const list = activeCategory.value === 'all'
        ? slots.value
        : slots.value.filter(s => s.category === activeCategory.value);
    return [...list].sort((a, b) => slotLabel(a.slot).localeCompare(slotLabel(b.slot), 'fr'));
});

function slotLabel(slot) {
    return SLOT_FR[slot] || slot;
}

function slotPercent(slot) {
    return slot.total === 0 ? 0 : Math.round((slot.completed / slot.total) * 100);
}

defineExpose({ activeCategory });
</script>
