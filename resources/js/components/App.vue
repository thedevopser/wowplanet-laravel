<template>
    <div class="min-h-screen dark bg-slate-900 text-slate-100 font-sans selection:bg-blue-500/30 overflow-x-hidden">
        <AppHeader />

        <main class="max-w-7xl mx-auto px-3 sm:px-4 py-6 sm:py-8">
            <div v-if="store.error" class="bg-red-500/10 border border-red-500/20 text-red-200 p-4 rounded-lg mb-6">
                {{ store.error }}
            </div>

            <router-view />
        </main>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useCharacterStore } from '../stores/character';
import AppHeader from './AppHeader.vue';

const store = useCharacterStore();

onMounted(() => {
    store.checkAuth();
});
</script>

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

@keyframes spin-reverse {
    from { transform: rotate(360deg); }
    to { transform: rotate(0deg); }
}
.animate-spin-reverse {
    animation: spin-reverse 1.5s linear infinite;
}
</style>
