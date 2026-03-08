<template>
    <div class="flex flex-col h-screen font-sans selection:bg-blue-500/30 overflow-hidden">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:bg-blue-600 focus:text-white focus:rounded-lg focus:shadow-lg">
            Aller au contenu principal
        </a>

        <AppHeader />

        <main id="main-content" class="flex-1 overflow-y-auto">
            <div class="max-w-7xl mx-auto px-3 sm:px-4 py-6 sm:py-8">
            <div v-if="store.error" role="alert" class="bg-red-500/10 border border-red-500/20 text-red-200 p-4 rounded-lg mb-6">
                {{ store.error }}
            </div>

            <router-view v-slot="{ Component }">
                <Transition name="page" mode="out-in">
                    <component :is="Component" />
                </Transition>
            </router-view>
            </div>
        </main>

        <AppFooter />
    </div>
</template>

<script setup>
import { onMounted, watch } from 'vue';
import { useCharacterStore } from '../stores/character';
import AppHeader from './AppHeader.vue';
import AppFooter from './AppFooter.vue';

const store = useCharacterStore();

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

applyTheme(store.theme);

watch(() => store.theme, applyTheme);

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
