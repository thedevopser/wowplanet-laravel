<template>
    <div class="flex flex-col h-screen font-sans selection:bg-blue-500/30 overflow-hidden">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:bg-blue-600 focus:text-white focus:rounded-lg focus:shadow-lg">
            Aller au contenu principal
        </a>

        <AppHeaderInertia />

        <main id="main-content" class="flex-1 overflow-y-auto" :class="isDatabase ? 'flex' : ''">
            <div v-if="isDatabase" class="flex-1 flex min-h-0">
                <slot />
            </div>
            <div v-else class="max-w-360 mx-auto px-3 sm:px-4 py-6 sm:py-8">
                <div v-if="store.error" role="alert" class="bg-red-500/10 border border-red-500/20 text-red-200 p-4 rounded-lg mb-6">
                    {{ store.error }}
                </div>
                <slot />
            </div>
        </main>

        <AppFooterInertia />

        <TaskSidebarInertia v-if="store.isAuthenticated" />
        <SessionExpiredBanner />
        <AuthRequiredBanner />
    </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCharacterStore } from '../stores/character';
import { useTaskStore } from '../stores/tasks';
import AppHeaderInertia from '../components/inertia/AppHeaderInertia.vue';
import AppFooterInertia from '../components/inertia/AppFooterInertia.vue';
import TaskSidebarInertia from '../components/inertia/TaskSidebarInertia.vue';
import SessionExpiredBanner from '../components/SessionExpiredBanner.vue';
import AuthRequiredBanner from '../components/AuthRequiredBanner.vue';

const page = usePage();
const store = useCharacterStore();
const taskStore = useTaskStore();

const isDatabase = computed(() => page.url.split('?')[0].startsWith('/base-de-donnees'));

function applyTheme(theme) {
    if (typeof document === 'undefined') return;
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

watch(() => store.theme, applyTheme);

watch(() => store.isAuthenticated, (authenticated) => {
    if (authenticated) {
        taskStore.fetchTasks();
    }
});

onMounted(() => {
    applyTheme(store.theme);
    store.checkAuth();
});
</script>
