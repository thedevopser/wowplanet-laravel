<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        leave-active-class="transition-all duration-200 ease-in"
        enter-from-class="opacity-0 translate-y-full"
        enter-to-class="opacity-100 translate-y-0"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-full"
    >
        <div
            v-if="visible"
            data-testid="discord-banner"
            class="fixed bottom-0 left-0 right-0 z-40 border-t border-white/15"
            style="background: linear-gradient(to right, #3730a3, #5865F2);"
        >
            <div class="max-w-7xl mx-auto px-3 sm:px-4 py-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <svg class="w-5 h-5 shrink-0 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.033.056a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>
                    </svg>
                    <span class="text-white text-sm font-medium truncate">Une idée d'amélioration ? Rejoins-nous sur Discord !</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a
                        href="https://discord.gg/wa49gGF8cr"
                        target="_blank"
                        rel="noopener noreferrer"
                        @click="dismiss"
                        class="bg-white font-bold text-xs px-4 py-1.5 rounded-md hover:bg-white/90 transition-colors"
                        style="color: #5865F2;"
                    >
                        Rejoindre
                    </a>
                    <button
                        @click="dismiss"
                        aria-label="Fermer la bannière Discord"
                        class="text-white/60 hover:text-white transition-colors p-1.5 rounded"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref } from 'vue';

const STORAGE_KEY = 'discord_banner_dismissed';

const emit = defineEmits(['dismissed']);

const visible = ref(!localStorage.getItem(STORAGE_KEY));

const dismiss = () => {
    localStorage.setItem(STORAGE_KEY, '1');
    visible.value = false;
    emit('dismissed');
};
</script>
