import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
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

    describe('handleSessionExpired', () => {
        it('vide l\'état auth et lève le flag sessionExpired', () => {
            const store = useCharacterStore();
            store.isAuthenticated = true;
            store.isAdmin = true;
            store.userCharacters = [{ id: 1, name: 'Thrall' }];
            store.error = 'une erreur';

            store.handleSessionExpired();

            expect(store.isAuthenticated).toBe(false);
            expect(store.isAdmin).toBe(false);
            expect(store.userCharacters).toEqual([]);
            expect(store.error).toBeNull();
            expect(store.sessionExpired).toBe(true);
        });

        it('est no-op si l\'utilisateur n\'est pas authentifié', () => {
            const store = useCharacterStore();
            store.isAuthenticated = false;
            store.sessionExpired = false;

            store.handleSessionExpired();

            expect(store.sessionExpired).toBe(false);
        });

        it('ne lève pas sessionExpired en double si appelé en rafale', () => {
            const store = useCharacterStore();
            store.isAuthenticated = true;

            store.handleSessionExpired();
            store.handleSessionExpired();

            expect(store.sessionExpired).toBe(true);
            expect(store.isAuthenticated).toBe(false);
        });
    });

    describe('clearSessionExpired', () => {
        it('remet sessionExpired à false', () => {
            const store = useCharacterStore();
            store.sessionExpired = true;

            store.clearSessionExpired();

            expect(store.sessionExpired).toBe(false);
        });
    });

    describe('toggleTheme', () => {
        beforeEach(() => localStorage.clear());

        it('switches from dark to light and back', () => {
            const store = useCharacterStore();

            store.toggleTheme();
            expect(store.theme).toBe('light');

            store.toggleTheme();
            expect(store.theme).toBe('dark');
        });

        it('persists the chosen theme', () => {
            const store = useCharacterStore();

            store.toggleTheme();

            expect(localStorage.getItem('wowplanet-theme')).toBe('light');
        });
    });

    describe('cross-character getters', () => {
        it('returns null while no cross-character data is loaded', () => {
            const store = useCharacterStore();

            expect(store.crossCharQuestIds).toBeNull();
            expect(store.crossCharAchievementIds).toBeNull();
            expect(store.crossCharRecipeIds).toBeNull();
        });

        it('builds a set from each list of completed ids', () => {
            const store = useCharacterStore();

            store._setCrossCharacterData({
                completedQuestIds: [1, 2],
                completedAchievementIds: [3],
                completedRecipeIds: [4, 5, 6],
            });

            expect(store.crossCharQuestIds).toEqual(new Set([1, 2]));
            expect(store.crossCharAchievementIds).toEqual(new Set([3]));
            expect(store.crossCharRecipeIds).toEqual(new Set([4, 5, 6]));
        });

        it('falls back to empty sets when the lists are missing', () => {
            const store = useCharacterStore();

            store._setCrossCharacterData({});

            expect(store.crossCharQuestIds).toEqual(new Set());
            expect(store.crossCharAchievementIds).toEqual(new Set());
            expect(store.crossCharRecipeIds).toEqual(new Set());
        });

        it('builds each set only once', () => {
            const store = useCharacterStore();

            store._setCrossCharacterData({ completedQuestIds: [1] });

            expect(store.crossCharQuestIds).toBe(store.crossCharQuestIds);
        });

        it('rebuilds the sets when new data arrives', () => {
            const store = useCharacterStore();

            store._setCrossCharacterData({ completedQuestIds: [1] });
            store._setCrossCharacterData({ completedQuestIds: [7, 8] });

            expect(store.crossCharQuestIds).toEqual(new Set([7, 8]));
        });
    });

    describe('completion lookups', () => {
        const crossCharacter = {
            completedQuestIds: [10],
            completedAchievementIds: [20],
            completedRecipeIds: [30],
            questOwners: { 10: 'Arthas' },
            achievementOwners: { 20: 'Jaina' },
            recipeOwners: { 30: 'Thrall' },
            bestFactionStandings: { 40: 'Exalté' },
            skillPointOwners: { 50: { 9: 100 } },
        };

        it('reports what another character has already completed', () => {
            const store = useCharacterStore();
            store._setCrossCharacterData(crossCharacter);

            expect(store.isQuestCompletedElsewhere(10)).toBe(true);
            expect(store.isAchievementCompletedElsewhere(20)).toBe(true);
            expect(store.isRecipeKnownElsewhere(30)).toBe(true);
        });

        it('reports nothing completed for unknown ids', () => {
            const store = useCharacterStore();
            store._setCrossCharacterData(crossCharacter);

            expect(store.isQuestCompletedElsewhere(99)).toBe(false);
            expect(store.isAchievementCompletedElsewhere(99)).toBe(false);
            expect(store.isRecipeKnownElsewhere(99)).toBe(false);
        });

        it('reports nothing completed without cross-character data', () => {
            const store = useCharacterStore();

            expect(store.isQuestCompletedElsewhere(10)).toBe(false);
            expect(store.isAchievementCompletedElsewhere(20)).toBe(false);
            expect(store.isRecipeKnownElsewhere(30)).toBe(false);
        });

        it('names the character holding each completion', () => {
            const store = useCharacterStore();
            store._setCrossCharacterData(crossCharacter);

            expect(store.getQuestOwner(10)).toBe('Arthas');
            expect(store.getAchievementOwner(20)).toBe('Jaina');
            expect(store.getRecipeOwner(30)).toBe('Thrall');
        });

        it('names nobody for an unknown completion', () => {
            const store = useCharacterStore();
            store._setCrossCharacterData(crossCharacter);

            expect(store.getQuestOwner(99)).toBeNull();
            expect(store.getAchievementOwner(99)).toBeNull();
            expect(store.getRecipeOwner(99)).toBeNull();
        });

        it('names nobody without cross-character data', () => {
            const store = useCharacterStore();

            expect(store.getQuestOwner(10)).toBeNull();
            expect(store.getAchievementOwner(20)).toBeNull();
            expect(store.getRecipeOwner(30)).toBeNull();
        });

        it('returns the best standing reached on a faction', () => {
            const store = useCharacterStore();
            store._setCrossCharacterData(crossCharacter);

            expect(store.getBestFactionStanding(40)).toBe('Exalté');
            expect(store.getBestFactionStanding(99)).toBeNull();
        });

        it('returns no standing without cross-character data', () => {
            const store = useCharacterStore();

            expect(store.getBestFactionStanding(40)).toBeNull();
        });

        it('returns the best skill points of a profession for an expansion', () => {
            const store = useCharacterStore();
            store._setCrossCharacterData(crossCharacter);

            expect(store.getBestSkillPoints(50, 9)).toBe(100);
        });

        it('returns no skill points for an expansion never levelled', () => {
            const store = useCharacterStore();
            store._setCrossCharacterData(crossCharacter);

            expect(store.getBestSkillPoints(50, 3)).toBeNull();
        });

        it('returns no skill points for an unknown profession', () => {
            const store = useCharacterStore();
            store._setCrossCharacterData(crossCharacter);

            expect(store.getBestSkillPoints(99, 9)).toBeNull();
        });

        it('returns no skill points without cross-character data', () => {
            const store = useCharacterStore();

            expect(store.getBestSkillPoints(50, 9)).toBeNull();
        });
    });

    describe('computeCrossCharacter', () => {
        it('stores the data straight away when it is already computed', async () => {
            axios.get = vi.fn().mockResolvedValue({
                data: { status: 'ready', data: { completedQuestIds: [1] } },
            });

            const store = useCharacterStore();
            await store.computeCrossCharacter();

            expect(store.crossCharacterStatus).toBe('ready');
            expect(store.crossCharacter).toEqual({ completedQuestIds: [1] });
        });

        it('does nothing while a computation is already running', async () => {
            axios.get = vi.fn();

            const store = useCharacterStore();
            store.crossCharacterStatus = 'loading';
            await store.computeCrossCharacter();

            expect(axios.get).not.toHaveBeenCalled();
        });

        it('does nothing when the data is already available', async () => {
            axios.get = vi.fn();

            const store = useCharacterStore();
            store.crossCharacterStatus = 'ready';
            await store.computeCrossCharacter();

            expect(axios.get).not.toHaveBeenCalled();
        });

        it('stays loading while the job is queued', async () => {
            axios.get = vi.fn().mockResolvedValue({ data: { status: 'computing', jobId: 'job-1' } });

            const store = useCharacterStore();
            await store.computeCrossCharacter();

            expect(store.crossCharacterStatus).toBe('loading');
        });

        it('reports an error when the request fails', async () => {
            axios.get = vi.fn().mockRejectedValue(new Error('boom'));

            const store = useCharacterStore();
            await store.computeCrossCharacter();

            expect(store.crossCharacterStatus).toBe('error');
        });
    });

    describe('cross-character job polling', () => {
        beforeEach(() => vi.useFakeTimers());
        afterEach(() => vi.useRealTimers());

        const startPolling = async (store, pollResponses) => {
            axios.get = vi.fn()
                .mockResolvedValueOnce({ data: { status: 'computing', jobId: 'job-1' } });

            for (const response of pollResponses) {
                axios.get.mockResolvedValueOnce(response);
            }

            await store.computeCrossCharacter();
        };

        it('loads the data once the job completes', async () => {
            const store = useCharacterStore();

            await startPolling(store, [
                { data: { status: 'completed' } },
                { data: { status: 'ready', data: { completedQuestIds: [5] } } },
            ]);
            await vi.advanceTimersByTimeAsync(3000);

            expect(store.crossCharacterStatus).toBe('ready');
            expect(store.crossCharacter).toEqual({ completedQuestIds: [5] });
        });

        it('keeps polling while the job is running', async () => {
            const store = useCharacterStore();

            await startPolling(store, [
                { data: { status: 'processing' } },
                { data: { status: 'completed' } },
                { data: { status: 'ready', data: { completedQuestIds: [5] } } },
            ]);

            await vi.advanceTimersByTimeAsync(3000);
            expect(store.crossCharacterStatus).toBe('loading');

            await vi.advanceTimersByTimeAsync(3000);
            expect(store.crossCharacterStatus).toBe('ready');
        });

        it('reports an error when the job fails', async () => {
            const store = useCharacterStore();

            await startPolling(store, [{ data: { status: 'failed' } }]);
            await vi.advanceTimersByTimeAsync(3000);

            expect(store.crossCharacterStatus).toBe('error');
        });

        it('reports an error when the job is unknown', async () => {
            const store = useCharacterStore();

            await startPolling(store, [{ data: { status: 'not_found' } }]);
            await vi.advanceTimersByTimeAsync(3000);

            expect(store.crossCharacterStatus).toBe('error');
        });

        it('reports an error when polling itself fails', async () => {
            const store = useCharacterStore();

            axios.get = vi.fn()
                .mockResolvedValueOnce({ data: { status: 'computing', jobId: 'job-1' } })
                .mockRejectedValueOnce(new Error('boom'));

            await store.computeCrossCharacter();
            await vi.advanceTimersByTimeAsync(3000);

            expect(store.crossCharacterStatus).toBe('error');
        });
    });

    describe('loadCrossCharacterData', () => {
        it('stores the data when it is ready', async () => {
            axios.get = vi.fn().mockResolvedValue({
                data: { status: 'ready', data: { completedQuestIds: [1] } },
            });

            const store = useCharacterStore();
            await store.loadCrossCharacterData();

            expect(store.crossCharacterStatus).toBe('ready');
            expect(store.crossCharacter).toEqual({ completedQuestIds: [1] });
        });

        it('reports the data as unavailable when the server has none', async () => {
            axios.get = vi.fn().mockResolvedValue({ data: { status: 'pending' } });

            const store = useCharacterStore();
            await store.loadCrossCharacterData();

            expect(store.crossCharacterStatus).toBe('not_available');
        });

        it('reports the data as unavailable when the request fails', async () => {
            axios.get = vi.fn().mockRejectedValue(new Error('boom'));

            const store = useCharacterStore();
            await store.loadCrossCharacterData();

            expect(store.crossCharacterStatus).toBe('not_available');
        });

        it('does not fetch data it already holds', async () => {
            axios.get = vi.fn();

            const store = useCharacterStore();
            store.crossCharacterStatus = 'ready';
            await store.loadCrossCharacterData();

            expect(axios.get).not.toHaveBeenCalled();
        });
    });

    describe('logout', () => {
        it('clears the cross-character data', async () => {
            axios.post = vi.fn().mockResolvedValue({});

            const store = useCharacterStore();
            store._setCrossCharacterData({ completedQuestIds: [1] });
            void store.crossCharQuestIds;

            await store.logout();

            expect(store.crossCharacter).toBeNull();
            expect(store.crossCharacterStatus).toBe('idle');
            expect(store.crossCharQuestIds).toBeNull();
        });
    });
});
