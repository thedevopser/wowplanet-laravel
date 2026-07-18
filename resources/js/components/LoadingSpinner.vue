<template>
    <!-- Overlay plein écran centré, identique en position à l'overlay de navigation
         (utils/navigationLoader.js) : tous les loaders de chargement de page sont ainsi
         cohérents, quel que soit le contenu déjà présent derrière. -->
    <div
        class="flex items-center justify-center"
        style="position:fixed;inset:0;z-index:9999;padding:1.5rem;background:rgba(15,23,42,0.88);backdrop-filter:blur(4px)"
        role="status"
        aria-live="polite"
    >
        <div class="card-glass rounded-2xl p-8 md:p-10 max-w-2xl mx-auto w-full">
            <div class="text-center">
                <div class="relative inline-block mb-6">
                    <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-blue-500 border-r-purple-500 animate-spin w-24 h-24"></div>
                    <div class="absolute inset-0 m-2 rounded-full border-4 border-transparent border-b-purple-500 border-l-blue-500 animate-spin-reverse w-20 h-20"></div>
                    <div class="relative flex items-center justify-center w-24 h-24">
                        <span class="text-4xl animate-pulse">{{ icon }}</span>
                    </div>
                </div>
                <h3 class="text-2xl font-bold mb-3 text-white">{{ title }}</h3>
                <p class="text-slate-400 mb-4">{{ subtitle }}</p>
                <div class="flex justify-center gap-2">
                    <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                    <div class="w-3 h-3 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                    <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                </div>
                <p v-if="hint" class="text-xs mt-6 text-slate-600">{{ hint }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    icon: { type: String, default: '⚔' },
    title: { type: String, default: 'Synchronisation en cours...' },
    subtitle: { type: String, default: 'Analyse de votre personnage via l\'API Blizzard' },
    hint: { type: String, default: '' },
});
</script>
