<template>
    <div role="status" aria-live="polite" class="animate-pulse">
        <!-- Card variant: single card skeleton -->
        <template v-if="variant === 'card'">
            <div class="card-glass rounded-2xl border p-5 sm:p-8 space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-slate-700/50 shrink-0"></div>
                    <div class="flex-1 space-y-3">
                        <div class="h-8 bg-slate-700/50 rounded-lg w-3/4"></div>
                        <div class="h-4 bg-slate-700/30 rounded w-1/2"></div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div v-for="i in 5" :key="i" class="h-14 bg-slate-700/30 rounded-xl flex-1"></div>
                </div>
            </div>
        </template>

        <!-- Grid variant: grid of card skeletons -->
        <template v-else-if="variant === 'grid'">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div v-for="i in count" :key="i" class="bg-slate-800/40 border border-white/5 p-5 rounded-2xl space-y-3">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-slate-700/50 shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-5 bg-slate-700/50 rounded w-2/3"></div>
                            <div class="h-3 bg-slate-700/30 rounded w-1/3"></div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <div v-for="j in 3" :key="j" class="h-5 bg-slate-700/30 rounded w-16"></div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Tabs variant: tab bar + content area -->
        <template v-else-if="variant === 'tabs'">
            <div class="space-y-6">
                <div class="flex gap-2 border-b border-white/10 pb-1">
                    <div v-for="i in 6" :key="i" class="h-10 bg-slate-700/30 rounded-t-xl" :style="{ width: (60 + Math.random() * 40) + 'px' }"></div>
                </div>
                <div class="card-glass rounded-2xl border p-5 sm:p-8 space-y-4">
                    <div class="h-6 bg-slate-700/50 rounded w-1/3"></div>
                    <div class="h-3 bg-slate-700/30 rounded-full w-full"></div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
                        <div v-for="i in 4" :key="i" class="h-20 bg-slate-700/30 rounded-xl"></div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Podium variant: for ClassStatsPage -->
        <template v-else-if="variant === 'podium'">
            <div class="flex items-end justify-center gap-4 sm:gap-6 max-w-3xl mx-auto pt-8">
                <div class="flex-1 max-w-[200px]">
                    <div class="card-glass rounded-2xl border p-4 sm:p-6 space-y-3">
                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-700/50"></div>
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-700/50"></div>
                        <div class="h-4 bg-slate-700/50 rounded w-2/3 mx-auto"></div>
                        <div class="h-8 bg-slate-700/30 rounded w-1/2 mx-auto"></div>
                    </div>
                </div>
                <div class="flex-1 max-w-[240px] -mt-8">
                    <div class="card-glass rounded-2xl border p-5 sm:p-8 space-y-4">
                        <div class="w-12 h-12 mx-auto rounded-full bg-slate-700/50"></div>
                        <div class="w-20 h-20 mx-auto rounded-2xl bg-slate-700/50"></div>
                        <div class="h-5 bg-slate-700/50 rounded w-2/3 mx-auto"></div>
                        <div class="h-10 bg-slate-700/30 rounded w-1/2 mx-auto"></div>
                    </div>
                </div>
                <div class="flex-1 max-w-[200px]">
                    <div class="card-glass rounded-2xl border p-4 sm:p-6 space-y-3">
                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-700/50"></div>
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-700/50"></div>
                        <div class="h-4 bg-slate-700/50 rounded w-2/3 mx-auto"></div>
                        <div class="h-8 bg-slate-700/30 rounded w-1/2 mx-auto"></div>
                    </div>
                </div>
            </div>
        </template>

        <span class="sr-only">Chargement en cours...</span>
    </div>
</template>

<script setup>
defineProps({
    variant: { type: String, default: 'card' },
    count: { type: Number, default: 8 },
});
</script>
