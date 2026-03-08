import { defineStore, acceptHMRUpdate } from 'pinia';
import axios from 'axios';

export const useCharacterStore = defineStore('character', {
    state: () => ({
        character: null,
        loading: false,
        error: null,
        isAuthenticated: false,
        isAdmin: false,
        userCharacters: [],
        classIcons: {},
        loadingCharacters: false,
        theme: localStorage.getItem('wowplanet-theme') || 'dark',
        expansions: [
            { id: 0, name: 'Classic' },
            { id: 1, name: 'The Burning Crusade' },
            { id: 2, name: 'Wrath of the Lich King' },
            { id: 3, name: 'Cataclysm' },
            { id: 4, name: 'Mists of Pandaria' },
            { id: 5, name: 'Warlords of Draenor' },
            { id: 6, name: 'Legion' },
            { id: 7, name: 'Battle for Azeroth' },
            { id: 8, name: 'Shadowlands' },
            { id: 9, name: 'Dragonflight' },
            { id: 10, name: 'The War Within' },
            { id: 11, name: 'Midnight' },
        ],
    }),

    getters: {
        latestExpansionId: (state) => state.expansions.at(-1)?.id ?? 10,
        expansionNamesDesc: (state) => [...state.expansions].reverse().map(e => e.name),
    },

    actions: {
        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('wowplanet-theme', this.theme);
        },

        async checkAuth() {
            try {
                const response = await axios.get('/api/auth/status');
                this.isAuthenticated = response.data.authenticated;
                this.isAdmin = response.data.isAdmin ?? false;
            } catch {
                this.isAuthenticated = false;
                this.isAdmin = false;
            }
        },

        async fetchCharacter(realm, name) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/character/${realm}/${name}`);
                this.character = response.data;
            } catch (err) {
                this.error = err.response?.data?.message || 'Failed to fetch character';
                this.character = null;
            } finally {
                this.loading = false;
            }
        },

        async fetchUserCharacters() {
            this.loadingCharacters = true;
            this.error = null;
            try {
                const response = await axios.get('/api/user/characters');
                this.userCharacters = response.data;
            } catch (err) {
                this.error = err.response?.data?.message || 'Impossible de récupérer vos personnages';
            } finally {
                this.loadingCharacters = false;
            }
        },

        async fetchClassIcons() {
            if (Object.keys(this.classIcons).length) return;
            try {
                const response = await axios.get('/api/class-icons');
                this.classIcons = response.data;
            } catch {
                // fallback: icons stay empty, UI will show initial letter
            }
        },

        async logout() {
            try {
                await axios.post('/api/auth/logout');
            } catch {
                // ignore
            }
            this.isAuthenticated = false;
            this.isAdmin = false;
            this.userCharacters = [];
            this.character = null;
        },
    },
});

if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(useCharacterStore, import.meta.hot));
}
