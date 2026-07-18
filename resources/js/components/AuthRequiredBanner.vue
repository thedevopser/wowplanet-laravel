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
            v-if="visible"
            data-testid="auth-required-banner"
            role="alert"
            class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 rounded-lg border border-amber-500/30 bg-slate-900/95 px-4 py-3 shadow-xl backdrop-blur-md"
        >
            <span class="text-amber-400 text-lg" aria-hidden="true">🔒</span>
            <span class="text-sm text-slate-200">Cette section nécessite une connexion Battle.net.</span>
            <button
                data-testid="auth-required-connect-btn"
                class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500 transition-colors whitespace-nowrap"
                @click="connect"
            >
                Se connecter
            </button>
            <button
                data-testid="auth-required-dismiss-btn"
                aria-label="Fermer"
                class="p-1 text-slate-400 hover:text-white transition-colors"
                @click="dismiss"
            >
                ✕
            </button>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const visible = ref(false);

onMounted(() => {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    if (params.get('auth') !== 'required') return;

    visible.value = true;

    // Retire le marqueur de l'URL sans recharger la page.
    params.delete('auth');
    const query = params.toString();
    const url = window.location.pathname + (query ? `?${query}` : '') + window.location.hash;
    window.history.replaceState(window.history.state, '', url);
});

function connect() {
    window.location.href = '/auth/blizzard/redirect';
}

function dismiss() {
    visible.value = false;
}
</script>
