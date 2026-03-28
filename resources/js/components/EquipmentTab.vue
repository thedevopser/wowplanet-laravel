<template>
    <div class="space-y-6">
        <!-- Header card -->
        <div class="card-glass rounded-2xl sm:rounded-3xl border p-5 sm:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-red-600/5 blur-3xl -mr-16 -mt-16"></div>
            <div class="relative z-10">
                <div class="flex justify-between items-end mb-2">
                    <h3 class="text-xl sm:text-2xl lg:text-3xl font-black text-white flex items-center gap-3">
                        <div class="w-2 h-6 sm:h-8 bg-red-500 rounded-full shadow-lg shadow-red-500/50"></div>
                        Équipement
                    </h3>
                    <div class="text-right">
                        <div class="text-2xl sm:text-3xl font-black text-red-400 font-mono">{{ character.ilvl }}</div>
                        <div class="text-[10px] sm:text-xs text-slate-500 font-mono uppercase font-bold">ilvl équipé</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop: Paper Doll (md+) -->
        <div class="hidden md:grid grid-cols-[1fr_auto_1fr] gap-4">
            <div class="space-y-2">
                <SlotCard v-for="slot in leftSlots" :key="slot" :item="itemBySlot(slot)" :slot-type="slot" />
            </div>

            <div class="flex flex-col items-center justify-center px-4">
                <div class="w-40 h-52 lg:w-48 lg:h-64 rounded-2xl overflow-hidden border-2 border-white/10 shadow-2xl shadow-red-500/10">
                    <img v-if="character.avatarUrl" :src="character.avatarUrl" alt="Avatar" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full bg-slate-800 flex items-center justify-center text-4xl text-slate-600">?</div>
                </div>
                <div class="mt-3 text-3xl font-black text-red-400 font-mono">{{ character.ilvl }}</div>
                <div class="text-xs text-slate-500 font-mono uppercase">ilvl équipé</div>
            </div>

            <div class="space-y-2">
                <SlotCard v-for="slot in rightSlots" :key="slot" :item="itemBySlot(slot)" :slot-type="slot" />
            </div>

            <div class="col-span-3 grid grid-cols-2 gap-4 mt-2">
                <SlotCard :item="itemBySlot('MAIN_HAND')" slot-type="MAIN_HAND" />
                <SlotCard :item="itemBySlot('OFF_HAND')" slot-type="OFF_HAND" />
            </div>
        </div>

        <!-- Mobile: Compact list (< md) -->
        <div class="md:hidden space-y-1">
            <component
                :is="itemBySlot(slot) ? 'a' : 'div'"
                v-for="slot in allSlots"
                :key="slot"
                :href="itemBySlot(slot) ? `https://www.wowhead.com/fr/item=${itemBySlot(slot).item_id}` : undefined"
                target="_blank"
                rel="noopener"
                :class="[
                    'flex items-center gap-3 px-3 py-2 rounded-xl transition-colors',
                    itemBySlot(slot) ? 'hover:bg-slate-800/60' : 'opacity-40'
                ]"
            >
                <div :class="['w-8 h-8 rounded-lg border overflow-hidden shrink-0', itemBySlot(slot) ? qualityBorder(itemBySlot(slot).quality) : 'border-slate-700']">
                    <img v-if="itemBySlot(slot)?.icon_url" :src="itemBySlot(slot).icon_url" :alt="itemBySlot(slot).name" class="w-8 h-8 object-cover" loading="lazy" />
                    <div v-else class="w-8 h-8 bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-600">{{ slotNames[slot]?.charAt(0) }}</div>
                </div>
                <span :class="['flex-1 text-sm truncate', itemBySlot(slot) ? qualityText(itemBySlot(slot).quality) : 'text-slate-600 italic']">
                    {{ itemBySlot(slot)?.name || slotNames[slot] }}
                </span>
                <span v-if="itemBySlot(slot)" class="text-xs font-mono text-slate-400 shrink-0">{{ itemBySlot(slot).item_level }}</span>
            </component>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    character: { type: Object, required: true },
});

const QUALITY_TEXT = {
    POOR: 'text-slate-500',
    COMMON: 'text-white',
    UNCOMMON: 'text-green-400',
    RARE: 'text-blue-400',
    EPIC: 'text-purple-400',
    LEGENDARY: 'text-orange-400',
    ARTIFACT: 'text-yellow-300',
};

const QUALITY_BORDER = {
    POOR: 'border-slate-500',
    COMMON: 'border-white/30',
    UNCOMMON: 'border-green-400',
    RARE: 'border-blue-400',
    EPIC: 'border-purple-400',
    LEGENDARY: 'border-orange-400',
    ARTIFACT: 'border-yellow-300',
};

const qualityText = (quality) => QUALITY_TEXT[quality] || 'text-white';
const qualityBorder = (quality) => QUALITY_BORDER[quality] || 'border-white/10';

const slotNames = {
    HEAD: 'Tête',
    NECK: 'Cou',
    SHOULDER: 'Épaule',
    BACK: 'Dos',
    CHEST: 'Torse',
    SHIRT: 'Chemise',
    TABARD: 'Tabard',
    WRIST: 'Poignets',
    HANDS: 'Mains',
    WAIST: 'Ceinture',
    LEGS: 'Jambes',
    FEET: 'Pieds',
    FINGER_1: 'Anneau 1',
    FINGER_2: 'Anneau 2',
    TRINKET_1: 'Bijou 1',
    TRINKET_2: 'Bijou 2',
    MAIN_HAND: 'Main droite',
    OFF_HAND: 'Main gauche',
};

const leftSlots = ['HEAD', 'NECK', 'SHOULDER', 'BACK', 'CHEST', 'SHIRT', 'TABARD', 'WRIST'];
const rightSlots = ['HANDS', 'WAIST', 'LEGS', 'FEET', 'FINGER_1', 'FINGER_2', 'TRINKET_1', 'TRINKET_2'];
const allSlots = [...leftSlots, ...rightSlots, 'MAIN_HAND', 'OFF_HAND'];

const equipmentMap = computed(() => {
    const map = {};
    for (const item of props.character.equipment || []) {
        map[item.slot] = item;
    }
    return map;
});

const itemBySlot = (slot) => equipmentMap.value[slot] || null;

const SlotCard = {
    props: {
        item: { type: Object, default: null },
        slotType: { type: String, required: true },
    },
    setup(props) {
        const label = computed(() => slotNames[props.slotType] || props.slotType);
        const textCls = computed(() => props.item ? qualityText(props.item.quality) : 'text-slate-600');
        const borderCls = computed(() => props.item ? qualityBorder(props.item.quality) : 'border-slate-700');

        return { label, textCls, borderCls };
    },
    template: `
        <component
            :is="item ? 'a' : 'div'"
            :href="item ? 'https://www.wowhead.com/fr/item=' + item.item_id : undefined"
            target="_blank"
            rel="noopener"
            :class="[
                'flex items-center gap-3 p-3 rounded-xl border transition-all',
                item ? 'bg-slate-800/40 border-white/5 hover:border-red-500/30 hover:bg-slate-800/60' : 'bg-slate-800/20 border-white/5 opacity-40'
            ]"
        >
            <div :class="['w-10 h-10 rounded-lg border overflow-hidden shrink-0', borderCls]">
                <img v-if="item && item.icon_url" :src="item.icon_url" :alt="item.name" class="w-10 h-10 object-cover" loading="lazy" />
                <div v-else class="w-10 h-10 bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-600">{{ label.charAt(0) }}</div>
            </div>
            <div class="flex-1 min-w-0">
                <div :class="['text-sm font-bold truncate', textCls]">{{ item ? item.name : label }}</div>
                <div class="text-[10px] text-slate-500 font-mono">{{ label }}</div>
            </div>
            <div v-if="item" class="text-sm font-mono font-bold text-slate-300 shrink-0">{{ item.item_level }}</div>
        </component>
    `,
};
</script>
