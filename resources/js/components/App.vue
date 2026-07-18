<template>
    <div class="flex flex-col h-screen font-sans selection:bg-blue-500/30 overflow-hidden">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:bg-blue-600 focus:text-white focus:rounded-lg focus:shadow-lg">
            Aller au contenu principal
        </a>

        <AppHeader />

        <main id="main-content" class="flex-1 overflow-y-auto" :class="isDatabase ? 'flex' : ''">
            <div v-if="isDatabase" class="flex-1 flex min-h-0">
                <router-view v-slot="{ Component }">
                    <component :is="Component" />
                </router-view>
            </div>
            <div v-else class="max-w-360 mx-auto px-3 sm:px-4 py-6 sm:py-8">
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

        <TaskSidebar v-if="store.isAuthenticated" />
        <SessionExpiredBanner />
        <AuthRequiredBanner />
    </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useCharacterStore } from '../stores/character';
import { useTaskStore } from '../stores/tasks';
import { useAuthGuard } from '../composables/useAuthGuard';
import AppHeader from './AppHeader.vue';
import AppFooter from './AppFooter.vue';
import TaskSidebar from './TaskSidebar.vue';
import SessionExpiredBanner from './SessionExpiredBanner.vue';
import AuthRequiredBanner from './AuthRequiredBanner.vue';

const route = useRoute();
const store = useCharacterStore();
const isDatabase = computed(() => route.path.startsWith('/base-de-donnees'));
const taskStore = useTaskStore();

useAuthGuard();

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

applyTheme(store.theme);

watch(() => store.theme, applyTheme);

watch(() => store.isAuthenticated, (authenticated) => {
    if (authenticated) {
        taskStore.fetchTasks();
    }
});

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
