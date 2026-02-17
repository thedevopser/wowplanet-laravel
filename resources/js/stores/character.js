import { defineStore } from 'pinia';
import axios from 'axios';

export const useCharacterStore = defineStore('character', {
    state: () => ({
        character: null,
        loading: false,
        error: null,
        currentView: 'home', // 'home' | 'character' | 'my-characters'
        isAuthenticated: false,
        userCharacters: [],
        loadingCharacters: false,
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

    actions: {
        async checkAuth() {
            try {
                const response = await axios.get('/api/auth/status');
                this.isAuthenticated = response.data.authenticated;
            } catch {
                this.isAuthenticated = false;
            }
        },

        async fetchCharacter(realm, name) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(`/api/character/${realm}/${name}`);
                this.character = response.data;
                this.currentView = 'character';
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
                this.currentView = 'my-characters';
            } catch (err) {
                this.error = err.response?.data?.message || 'Impossible de récupérer vos personnages';
            } finally {
                this.loadingCharacters = false;
            }
        },

        async logout() {
            try {
                await axios.post('/api/auth/logout');
            } catch {
                // ignore
            }
            this.isAuthenticated = false;
            this.userCharacters = [];
            this.currentView = 'home';
            this.character = null;
        },

        goHome() {
            this.character = null;
            this.error = null;
            this.currentView = 'home';
        },
    },
});
