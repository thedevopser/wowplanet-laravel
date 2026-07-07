<template>
    <div class="flex min-h-0 flex-1">
        <!-- Sidebar (desktop lg+) -->
        <aside class="hidden lg:flex flex-col w-56 shrink-0 bg-slate-900/60 backdrop-blur-sm border-r border-white/5">
            <div class="p-4 border-b border-white/5">
                <router-link to="/base-de-donnees" class="text-sm font-black text-slate-300 hover:text-white transition-colors">
                    Base de données
                </router-link>
            </div>
            <nav class="flex-1 py-2 overflow-y-auto no-scrollbar">
                <div v-for="section in sections" :key="section.key" class="mb-0.5">
                    <!-- Section header -->
                    <button
                        @click="onSectionClick(section)"
                        :class="[
                            'w-full flex items-center gap-2.5 px-4 py-2 text-sm font-medium transition-all',
                            isSectionActive(section.path)
                                ? section.activeClass
                                : 'text-slate-400 hover:text-white hover:bg-slate-800/60'
                        ]"
                    >
                        <div class="w-4.5 h-4.5 shrink-0"><CategoryIcon :category="section.icon" /></div>
                        <span class="flex-1 text-left truncate">{{ section.label }}</span>
                        <span v-if="store.counts[section.countKey]" class="text-[9px] font-mono text-slate-600">{{ formatCount(store.counts[section.countKey]) }}</span>
                        <svg
                            :class="['w-3 h-3 text-slate-600 transition-transform duration-200', store.expanded[section.key] ? 'rotate-90' : '']"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>

                    <!-- Sub-items (accordion) -->
                    <div
                        :class="['grid transition-[grid-template-rows] duration-200 ease-out', store.expanded[section.key] ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]']"
                    >
                        <div class="overflow-hidden">
                            <div v-if="store.loading[section.key]" class="py-2 pl-11 text-[10px] text-slate-600">
                                Chargement...
                            </div>
                            <template v-else-if="store.subCategories[section.key]">
                                <router-link
                                    :to="section.path"
                                    :class="[
                                        'block py-1.5 pl-11 pr-4 text-xs transition-colors truncate',
                                        isExactActive(section.path) ? section.subActiveClass : 'text-slate-500 hover:text-slate-300'
                                    ]"
                                >Tous</router-link>
                                <router-link
                                    v-for="sub in store.subCategories[section.key]"
                                    :key="sub.slug"
                                    :to="section.path + '/' + sub.slug"
                                    :class="[
                                        'flex items-center justify-between py-1.5 pl-11 pr-4 text-xs transition-colors',
                                        isExactActive(section.path + '/' + sub.slug) ? section.subActiveClass : 'text-slate-500 hover:text-slate-300'
                                    ]"
                                >
                                    <span class="truncate">{{ sub.name }}</span>
                                    <span class="text-[9px] font-mono text-slate-700 shrink-0 ml-2">{{ sub.count }}</span>
                                </router-link>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>
            <div class="p-4 border-t border-white/5">
                <router-link to="/" class="text-xs text-slate-500 hover:text-slate-300 transition-colors flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Retour au site
                </router-link>
            </div>
        </aside>

        <!-- Main content area -->
        <div class="flex-1 min-w-0 flex flex-col">
            <!-- Top bar mobile: main sections -->
            <nav class="lg:hidden flex items-center gap-1.5 px-3 py-2 bg-slate-900/40 border-b border-white/5 overflow-x-auto no-scrollbar">
                <router-link
                    v-for="section in sections"
                    :key="section.path"
                    :to="section.path"
                    :class="[
                        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border whitespace-nowrap transition-all shrink-0',
                        isSectionActive(section.path)
                            ? section.pillActiveClass
                            : 'bg-slate-800/60 border-white/10 text-slate-500 hover:text-slate-300 hover:border-white/20'
                    ]"
                >
                    <div class="w-3.5 h-3.5 shrink-0"><CategoryIcon :category="section.icon" /></div>
                    {{ section.shortLabel }}
                </router-link>
            </nav>

            <!-- Mobile sub-categories row -->
            <nav
                v-if="activeMobileSubCategories.length > 0"
                class="lg:hidden flex items-center gap-1 px-3 py-1.5 bg-slate-900/20 border-b border-white/3 overflow-x-auto no-scrollbar"
            >
                <router-link
                    :to="activeMobileSection.path"
                    :class="[
                        'px-2.5 py-1 rounded text-[11px] font-medium whitespace-nowrap transition-colors shrink-0',
                        isExactActive(activeMobileSection.path) ? 'text-white bg-slate-700/60' : 'text-slate-500 hover:text-slate-300'
                    ]"
                >Tous</router-link>
                <router-link
                    v-for="sub in activeMobileSubCategories"
                    :key="sub.slug"
                    :to="activeMobileSection.path + '/' + sub.slug"
                    :class="[
                        'px-2.5 py-1 rounded text-[11px] font-medium whitespace-nowrap transition-colors shrink-0',
                        isExactActive(activeMobileSection.path + '/' + sub.slug) ? 'text-white bg-slate-700/60' : 'text-slate-500 hover:text-slate-300'
                    ]"
                >{{ sub.name }}</router-link>
            </nav>

            <!-- Page content -->
            <div class="flex-1 overflow-y-auto">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <router-view />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useDatabaseSidebarStore } from '../stores/databaseSidebar';
import CategoryIcon from './CategoryIcon.vue';

const route = useRoute();
const router = useRouter();
const store = useDatabaseSidebarStore();

const sections = [
    {
        key: 'mounts',
        path: '/base-de-donnees/montures',
        label: 'Montures',
        shortLabel: 'Montures',
        icon: 'mounts',
        countKey: 'mounts',
        activeClass: 'bg-amber-600/10 text-amber-300',
        subActiveClass: 'text-amber-400 font-semibold',
        pillActiveClass: 'bg-amber-500/15 border-amber-500/30 text-amber-400',
    },
    {
        key: 'achievements',
        path: '/base-de-donnees/hauts-faits',
        label: 'Hauts-faits',
        shortLabel: 'H-faits',
        icon: 'achievements',
        countKey: 'achievements',
        activeClass: 'bg-amber-600/10 text-amber-300',
        subActiveClass: 'text-amber-400 font-semibold',
        pillActiveClass: 'bg-amber-500/15 border-amber-500/30 text-amber-400',
    },
    {
        key: 'quests',
        path: '/base-de-donnees/quetes',
        label: 'Quêtes',
        shortLabel: 'Quêtes',
        icon: 'quests',
        countKey: 'quests',
        activeClass: 'bg-blue-600/10 text-blue-300',
        subActiveClass: 'text-blue-400 font-semibold',
        pillActiveClass: 'bg-blue-500/15 border-blue-500/30 text-blue-400',
    },
    {
        key: 'pets',
        path: '/base-de-donnees/mascottes',
        label: 'Mascottes',
        shortLabel: 'Mascottes',
        icon: 'pets',
        countKey: 'pets',
        activeClass: 'bg-blue-600/10 text-blue-300',
        subActiveClass: 'text-blue-400 font-semibold',
        pillActiveClass: 'bg-blue-500/15 border-blue-500/30 text-blue-400',
    },
    {
        key: 'decors',
        path: '/base-de-donnees/decorations',
        label: 'Décorations',
        shortLabel: 'Décos',
        icon: 'decor',
        countKey: 'decors',
        activeClass: 'bg-violet-600/10 text-violet-300',
        subActiveClass: 'text-violet-400 font-semibold',
        pillActiveClass: 'bg-violet-500/15 border-violet-500/30 text-violet-400',
    },
    {
        key: 'appearances',
        path: '/base-de-donnees/garde-robe',
        label: 'Garde-robe',
        shortLabel: 'Garde-robe',
        icon: 'transmog',
        countKey: 'appearances',
        activeClass: 'bg-violet-600/10 text-violet-300',
        subActiveClass: 'text-violet-400 font-semibold',
        pillActiveClass: 'bg-violet-500/15 border-violet-500/30 text-violet-400',
    },
    {
        key: 'professions',
        path: '/base-de-donnees/professions',
        label: 'Professions',
        shortLabel: 'Profess.',
        icon: 'professions',
        countKey: 'recipes',
        activeClass: 'bg-emerald-600/10 text-emerald-300',
        subActiveClass: 'text-emerald-400 font-semibold',
        pillActiveClass: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400',
    },
];

function isSectionActive(path) {
    return route.path === path || route.path.startsWith(path + '/');
}

function isExactActive(path) {
    return route.path === path;
}

function formatCount(n) {
    if (!n) return '';
    return n.toLocaleString('fr-FR');
}

function onSectionClick(section) {
    store.toggleSection(section.key);
    router.push(section.path);
}

// Mobile: find active section and its sub-categories
const activeMobileSection = computed(() => {
    return sections.find(s => isSectionActive(s.path)) || null;
});

const activeMobileSubCategories = computed(() => {
    if (!activeMobileSection.value) return [];
    return store.subCategories[activeMobileSection.value.key] || [];
});

// Auto-expand active section on route change
watch(() => route.path, (path) => {
    store.expandActiveSection(path);
}, { immediate: false });

onMounted(() => {
    store.fetchCounts();
    store.expandActiveSection(route.path);
});
</script>
