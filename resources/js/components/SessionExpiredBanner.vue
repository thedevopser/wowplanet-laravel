<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div
            v-if="store.sessionExpired"
            data-testid="session-expired-banner"
            role="alert"
            class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 rounded-lg border border-amber-500/30 bg-slate-900/95 px-4 py-3 shadow-xl backdrop-blur-md"
        >
            <span class="text-amber-400 text-lg" aria-hidden="true">⚠</span>
            <span class="text-sm text-slate-200 whitespace-nowrap">Votre session a expiré.</span>
            <button
                data-testid="reconnect-btn"
                class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500 transition-colors whitespace-nowrap"
                @click="reconnect"
            >
                Se reconnecter
            </button>
            <button
                data-testid="dismiss-btn"
                :aria-label="'Fermer'"
                class="p-1 text-slate-400 hover:text-white transition-colors"
                @click="store.clearSessionExpired()"
            >
                ✕
            </button>
        </div>
    </Transition>
</template>

<script setup>
import { useCharacterStore } from '../stores/character';

const store = useCharacterStore();

function reconnect() {
    window.location.href = '/auth/blizzard/redirect';
}
</script>
