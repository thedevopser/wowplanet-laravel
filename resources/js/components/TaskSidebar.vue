<template>
    <div>
        <!-- Toggle Button -->
        <button
            data-testid="sidebar-toggle"
            class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-12 h-12 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-900/30 transition-all duration-200 hover:scale-110"
            @click="taskStore.toggleSidebar()"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 transition-transform duration-200" :class="{ 'rotate-180': taskStore.sidebarOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
            <span
                v-if="taskStore.totalPendingCount > 0"
                data-testid="pending-badge"
                class="absolute -top-1 -right-1 flex items-center justify-center min-w-5 h-5 px-1 text-xs font-bold bg-red-500 text-white rounded-full"
            >
                {{ taskStore.totalPendingCount }}
            </span>
        </button>

        <!-- Overlay -->
        <Transition name="fade">
            <div
                v-if="taskStore.sidebarOpen"
                class="fixed inset-0 z-40 bg-black/40 sm:hidden"
                @click="taskStore.toggleSidebar()"
            />
        </Transition>

        <!-- Sidebar Panel -->
        <Transition name="slide">
            <div
                v-if="taskStore.sidebarOpen"
                data-testid="sidebar-panel"
                class="fixed top-0 right-0 z-50 h-full w-80 flex flex-col bg-gray-900/95 backdrop-blur-md border-l border-white/10 shadow-2xl"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
                    <h2 class="text-lg font-semibold text-white">Mes t&acirc;ches</h2>
                    <button
                        class="p-1 text-gray-400 hover:text-white transition-colors"
                        @click="taskStore.toggleSidebar()"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto px-3 py-3 space-y-2">
                    <div v-if="displayedCharacters.length === 0" class="text-center text-gray-500 py-8 text-sm">
                        Ouvrez la fiche d'un personnage pour lui ajouter des t&acirc;ches.
                    </div>

                    <div
                        v-for="char in displayedCharacters"
                        :key="`${char.realm_slug}-${char.character_name}`"
                        data-testid="character-section"
                    >
                        <!-- Character Header -->
                        <button
                            data-testid="character-header"
                            class="w-full flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/5 transition-colors"
                            @click="toggleCharacter(char.realm_slug, char.character_name)"
                        >
                            <img
                                v-if="getCharacterInfo(char)?.avatarUrl"
                                :src="getCharacterInfo(char).avatarUrl"
                                :alt="getCharacterInfo(char)?.name || char.character_name"
                                class="w-8 h-8 rounded-full border border-white/20"
                            >
                            <div v-else class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-xs text-gray-400 border border-white/20">
                                {{ char.character_name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="flex-1 text-left">
                                <div class="text-sm font-medium text-white">{{ capitalize(getCharacterInfo(char)?.name || char.character_name) }}</div>
                                <div class="text-xs text-gray-400">{{ getCharacterInfo(char)?.realm?.name || char.realm_slug }}</div>
                            </div>
                            <span class="text-xs text-emerald-400 font-medium">
                                {{ taskStore.pendingCount(char.realm_slug, char.character_name) }}
                            </span>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-4 h-4 text-gray-500 transition-transform duration-200"
                                :class="{ 'rotate-90': isExpanded(char.realm_slug, char.character_name) }"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <!-- Tasks List (expanded) -->
                        <div v-if="isExpanded(char.realm_slug, char.character_name)" class="ml-4 mt-1 space-y-1">
                            <div
                                v-for="task in taskStore.characterTasks(char.realm_slug, char.character_name)"
                                :key="task.id"
                                data-testid="task-item"
                                class="flex items-center gap-2 px-2 py-1.5 rounded-md group hover:bg-white/5 transition-colors"
                            >
                                <button
                                    data-testid="task-checkbox"
                                    class="flex-shrink-0 w-4 h-4 rounded border transition-colors"
                                    :class="task.is_completed
                                        ? 'bg-emerald-500 border-emerald-500'
                                        : 'border-gray-500 hover:border-emerald-400'"
                                    @click="taskStore.toggleTask(task.id)"
                                >
                                    <svg v-if="task.is_completed" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                                <span class="flex-1 text-sm truncate" :class="task.is_completed ? 'text-gray-500 line-through' : 'text-gray-200'">
                                    {{ task.name }}
                                </span>
                                <span
                                    class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                    :class="{
                                        'bg-emerald-500/20 text-emerald-400': task.reset_type === 'daily',
                                        'bg-blue-500/20 text-blue-400': task.reset_type === 'weekly',
                                        'bg-purple-500/20 text-purple-400': task.reset_type === 'monthly',
                                    }"
                                >
                                    {{ { daily: 'J', weekly: 'H', monthly: 'M' }[task.reset_type] }}
                                </span>
                                <button
                                    data-testid="delete-task-btn"
                                    class="opacity-0 group-hover:opacity-100 p-0.5 text-gray-500 hover:text-red-400 transition-all"
                                    @click="taskStore.deleteTask(task.id)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Add Task Button / Form -->
                            <div v-if="!showFormFor(char.realm_slug, char.character_name)" class="px-2 py-1">
                                <button
                                    data-testid="add-task-btn"
                                    class="text-xs text-emerald-400 hover:text-emerald-300 transition-colors"
                                    @click="openForm(char.realm_slug, char.character_name)"
                                >
                                    + Nouvelle t&acirc;che
                                </button>
                            </div>

                            <form
                                v-else
                                data-testid="task-form"
                                class="px-2 py-2 space-y-2"
                                @submit.prevent="submitForm(char.realm_slug, char.character_name)"
                            >
                                <input
                                    data-testid="task-name-input"
                                    v-model="formName"
                                    type="text"
                                    placeholder="Nom de la t&acirc;che"
                                    class="w-full px-2 py-1.5 text-sm bg-white/5 border border-white/10 rounded-md text-white placeholder-gray-500 focus:outline-none focus:border-emerald-500"
                                    required
                                >
                                <div class="flex gap-2">
                                    <select
                                        data-testid="task-reset-select"
                                        v-model="formResetType"
                                        class="flex-1 px-2 py-1.5 text-sm bg-gray-800 border border-white/10 rounded-md text-white focus:outline-none focus:border-emerald-500"
                                    >
                                        <option value="daily" class="bg-gray-800 text-white">Journalier</option>
                                        <option value="weekly" class="bg-gray-800 text-white">Hebdomadaire</option>
                                        <option value="monthly" class="bg-gray-800 text-white">Mensuel</option>
                                    </select>
                                    <button
                                        type="submit"
                                        class="px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-500 text-white rounded-md transition-colors"
                                    >
                                        OK
                                    </button>
                                    <button
                                        type="button"
                                        class="px-2 py-1.5 text-sm text-gray-400 hover:text-white transition-colors"
                                        @click="closeForm()"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useTaskStore } from '../stores/tasks';
import { useCharacterStore } from '../stores/character';

const taskStore = useTaskStore();
const characterStore = useCharacterStore();
const route = useRoute();

onMounted(() => {
    if (characterStore.isAuthenticated && !characterStore.userCharacters.length) {
        characterStore.fetchUserCharacters();
    }
});

const currentCharacter = computed(() => {
    if (route.name !== 'character' || !route.params.realm || !route.params.name) return null;
    return {
        realm_slug: route.params.realm,
        character_name: route.params.name.toLowerCase(),
    };
});

const isCurrentCharacterInList = computed(() => {
    if (!currentCharacter.value) return true;
    return taskStore.charactersWithTasks.some(
        c => c.realm_slug === currentCharacter.value.realm_slug
            && c.character_name === currentCharacter.value.character_name
    );
});

const displayedCharacters = computed(() => {
    const list = [...taskStore.charactersWithTasks];
    if (currentCharacter.value && !isCurrentCharacterInList.value) {
        list.unshift(currentCharacter.value);
    }
    return list;
});

const expandedCharacters = ref(new Set());
const activeForm = ref(null);
const formName = ref('');
const formResetType = ref('daily');

function toggleCharacter(realm, name) {
    const key = `${realm}|${name}`;
    if (expandedCharacters.value.has(key)) {
        expandedCharacters.value.delete(key);
    } else {
        expandedCharacters.value.add(key);
    }
}

function isExpanded(realm, name) {
    return expandedCharacters.value.has(`${realm}|${name}`);
}

function getCharacterInfo(char) {
    return characterStore.userCharacters.find(
        c => c.realmSlug === char.realm_slug && c.name?.toLowerCase() === char.character_name
    );
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function showFormFor(realm, name) {
    return activeForm.value === `${realm}|${name}`;
}

function openForm(realm, name) {
    activeForm.value = `${realm}|${name}`;
    formName.value = '';
    formResetType.value = 'daily';
}

function closeForm() {
    activeForm.value = null;
    formName.value = '';
}

async function submitForm(realm, name) {
    if (!formName.value.trim()) return;
    await taskStore.createTask(realm, name, formName.value.trim(), formResetType.value);
    closeForm();
}
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.25s ease;
}
.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
