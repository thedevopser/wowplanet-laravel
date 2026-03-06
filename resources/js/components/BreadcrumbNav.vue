<template>
    <nav class="flex items-center gap-1.5 text-xs sm:text-sm text-slate-500 mb-6">
        <router-link to="/" class="hover:text-slate-300 transition-colors">WowPlanet</router-link>
        <template v-for="(crumb, i) in crumbs" :key="i">
            <span class="text-slate-700">/</span>
            <router-link
                v-if="crumb.to && i < crumbs.length - 1"
                :to="crumb.to"
                class="hover:text-slate-300 transition-colors"
            >{{ crumb.label }}</router-link>
            <span v-else class="text-slate-300 font-medium">{{ crumb.label }}</span>
        </template>
    </nav>
</template>

<script setup>
defineProps({
    crumbs: {
        type: Array,
        required: true,
    },
});
</script>
