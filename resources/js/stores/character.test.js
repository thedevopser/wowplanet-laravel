import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import axios from 'axios';
import { useCharacterStore } from './character';

vi.mock('axios');

describe('useCharacterStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('has correct default values', () => {
            const store = useCharacterStore();

            expect(store.character).toBeNull();
            expect(store.loading).toBe(false);
            expect(store.error).toBeNull();
            expect(store.isAuthenticated).toBe(false);
            expect(store.userCharacters).toEqual([]);
            expect(store.classIcons).toEqual({});
            expect(store.loadingCharacters).toBe(false);
        });

        it('contains 12 expansions from Classic to Midnight', () => {
            const store = useCharacterStore();

            expect(store.expansions).toHaveLength(12);
            expect(store.expansions[0]).toEqual({ id: 0, name: 'Classic' });
            expect(store.expansions[11]).toEqual({ id: 11, name: 'Midnight' });
        });
    });

    describe('checkAuth', () => {
        it('sets isAuthenticated to true when API returns authenticated', async () => {
            axios.get.mockResolvedValue({ data: { authenticated: true } });
            const store = useCharacterStore();

            await store.checkAuth();

            expect(axios.get).toHaveBeenCalledWith('/api/auth/status');
            expect(store.isAuthenticated).toBe(true);
        });

        it('sets isAuthenticated to false when API returns not authenticated', async () => {
            axios.get.mockResolvedValue({ data: { authenticated: false } });
            const store = useCharacterStore();

            await store.checkAuth();

            expect(store.isAuthenticated).toBe(false);
        });

        it('sets isAuthenticated to false on error', async () => {
            axios.get.mockRejectedValue(new Error('Network error'));
            const store = useCharacterStore();

            await store.checkAuth();

            expect(store.isAuthenticated).toBe(false);
        });
    });

    describe('fetchCharacter', () => {
        it('sets character data on success', async () => {
            const characterData = { name: 'Arthas', level: 80 };
            axios.get.mockResolvedValue({ data: characterData });
            const store = useCharacterStore();

            await store.fetchCharacter('hyjal', 'arthas');

            expect(axios.get).toHaveBeenCalledWith('/api/character/hyjal/arthas');
            expect(store.character).toEqual(characterData);
            expect(store.loading).toBe(false);
            expect(store.error).toBeNull();
        });

        it('sets loading to true during fetch', async () => {
            let resolvePromise;
            axios.get.mockReturnValue(new Promise(resolve => { resolvePromise = resolve; }));
            const store = useCharacterStore();

            const promise = store.fetchCharacter('hyjal', 'arthas');
            expect(store.loading).toBe(true);

            resolvePromise({ data: { name: 'Arthas' } });
            await promise;
            expect(store.loading).toBe(false);
        });

        it('sets error message on API failure', async () => {
            axios.get.mockRejectedValue({ response: { data: { message: 'Character not found' } } });
            const store = useCharacterStore();

            await store.fetchCharacter('hyjal', 'unknown');

            expect(store.error).toBe('Character not found');
            expect(store.character).toBeNull();
            expect(store.loading).toBe(false);
        });

        it('uses fallback error message when no response message', async () => {
            axios.get.mockRejectedValue(new Error('Network error'));
            const store = useCharacterStore();

            await store.fetchCharacter('hyjal', 'unknown');

            expect(store.error).toBe('Failed to fetch character');
        });
    });

    describe('fetchUserCharacters', () => {
        it('sets userCharacters on success', async () => {
            const characters = [{ name: 'Arthas' }, { name: 'Jaina' }];
            axios.get.mockResolvedValue({ data: characters });
            const store = useCharacterStore();

            await store.fetchUserCharacters();

            expect(axios.get).toHaveBeenCalledWith('/api/user/characters');
            expect(store.userCharacters).toEqual(characters);
            expect(store.loadingCharacters).toBe(false);
        });

        it('sets loadingCharacters during fetch', async () => {
            let resolvePromise;
            axios.get.mockReturnValue(new Promise(resolve => { resolvePromise = resolve; }));
            const store = useCharacterStore();

            const promise = store.fetchUserCharacters();
            expect(store.loadingCharacters).toBe(true);

            resolvePromise({ data: [] });
            await promise;
            expect(store.loadingCharacters).toBe(false);
        });

        it('sets error on failure', async () => {
            axios.get.mockRejectedValue({ response: { data: { message: 'Non autorisé' } } });
            const store = useCharacterStore();

            await store.fetchUserCharacters();

            expect(store.error).toBe('Non autorisé');
        });
    });

    describe('fetchClassIcons', () => {
        it('fetches class icons on first call', async () => {
            const icons = { 1: 'warrior.png', 2: 'paladin.png' };
            axios.get.mockResolvedValue({ data: icons });
            const store = useCharacterStore();

            await store.fetchClassIcons();

            expect(axios.get).toHaveBeenCalledWith('/api/class-icons');
            expect(store.classIcons).toEqual(icons);
        });

        it('does not refetch when icons already loaded', async () => {
            const store = useCharacterStore();
            store.classIcons = { 1: 'warrior.png' };

            await store.fetchClassIcons();

            expect(axios.get).not.toHaveBeenCalled();
        });

        it('keeps empty icons on error', async () => {
            axios.get.mockRejectedValue(new Error('fail'));
            const store = useCharacterStore();

            await store.fetchClassIcons();

            expect(store.classIcons).toEqual({});
        });
    });

    describe('logout', () => {
        it('calls logout API and resets state', async () => {
            axios.post.mockResolvedValue({});
            const store = useCharacterStore();
            store.isAuthenticated = true;
            store.userCharacters = [{ name: 'Arthas' }];
            store.character = { name: 'Arthas' };

            await store.logout();

            expect(axios.post).toHaveBeenCalledWith('/api/auth/logout');
            expect(store.isAuthenticated).toBe(false);
            expect(store.userCharacters).toEqual([]);
            expect(store.character).toBeNull();
        });

        it('resets state even on API error', async () => {
            axios.post.mockRejectedValue(new Error('fail'));
            const store = useCharacterStore();
            store.isAuthenticated = true;

            await store.logout();

            expect(store.isAuthenticated).toBe(false);
            expect(store.userCharacters).toEqual([]);
            expect(store.character).toBeNull();
        });
    });
});
