import { defineStore, acceptHMRUpdate } from 'pinia';
import axios from 'axios';

export const useDatabaseSidebarStore = defineStore('databaseSidebar', {
    state: () => ({
        counts: {},
        expanded: {},
        subCategories: {},
        loading: {},
    }),

    actions: {
        async fetchCounts() {
            if (Object.keys(this.counts).length > 0) return;
            try {
                const { data } = await axios.get('/api/database/counts');
                this.counts = data;
            } catch {
                // silently fail
            }
        },

        async fetchSubCategories(sectionKey) {
            if (this.subCategories[sectionKey]) return;
            this.loading[sectionKey] = true;
            try {
                const { data } = await axios.get(`/api/database/subcategories/${sectionKey}`);
                this.subCategories[sectionKey] = data.items;
            } catch {
                this.subCategories[sectionKey] = [];
            } finally {
                this.loading[sectionKey] = false;
            }
        },

        async toggleSection(sectionKey) {
            if (this.expanded[sectionKey]) {
                this.expanded[sectionKey] = false;
            } else {
                this.expanded[sectionKey] = true;
                await this.fetchSubCategories(sectionKey);
            }
        },

        async expandActiveSection(routePath) {
            const sectionKeys = ['mounts', 'achievements', 'quests', 'pets', 'decors', 'appearances', 'professions'];
            const pathMap = {
                '/base-de-donnees/montures': 'mounts',
                '/base-de-donnees/hauts-faits': 'achievements',
                '/base-de-donnees/quetes': 'quests',
                '/base-de-donnees/mascottes': 'pets',
                '/base-de-donnees/decorations': 'decors',
                '/base-de-donnees/garde-robe': 'appearances',
                '/base-de-donnees/professions': 'professions',
            };

            let activeKey = null;
            for (const [path, key] of Object.entries(pathMap)) {
                if (routePath.startsWith(path)) {
                    activeKey = key;
                    break;
                }
            }

            // Collapse all others, expand active
            for (const key of sectionKeys) {
                if (key === activeKey) {
                    this.expanded[key] = true;
                    await this.fetchSubCategories(key);
                } else {
                    this.expanded[key] = false;
                }
            }
        },
    },
});

if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(useDatabaseSidebarStore, import.meta.hot));
}
