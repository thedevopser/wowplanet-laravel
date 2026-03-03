<template>
    <div :class="sizeClass" class="rounded-lg border border-white/10 overflow-hidden shrink-0 relative">
        <img
            v-if="src && !errored"
            :src="src"
            :alt="alt"
            :class="sizeClass"
            class="rounded-lg object-cover"
            loading="eager"
            @error="errored = true"
        />
        <div
            v-else
            :class="[sizeClass, fallbackTextClass]"
            class="bg-slate-800 flex items-center justify-center font-bold"
        >{{ fallback }}</div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    fallback: { type: String, default: '?' },
    size: { type: String, default: 'sm' },
});

const errored = ref(false);

const sizeClass = props.size === 'lg' ? 'w-10 h-10' : 'w-8 h-8';
const fallbackTextClass = props.size === 'lg' ? 'text-sm' : 'text-[10px]';
</script>
