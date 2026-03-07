import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import axios from 'axios';
import { mountWithPlugins } from '../tests/helpers';
import AccountScorePage from './AccountScorePage.vue';

vi.mock('axios');

const virtualProfile = {
    characterCount: 3,
    cachedAt: '2024-03-01T10:00:00Z',
    collections: {
        1: {
            quests: { completed: 50, total: 100, zones: [] },
            achievements: { completed: 30, total: 100, categories: [] },
            reputations: { completed: 10, total: 20 },
        },
    },
    mounts: [{ id: 1, is_completed: true, source: 'Drop' }, { id: 2, is_completed: false, source: 'Drop' }],
    pets: [{ id: 1, is_completed: true, source: 'Quête' }],
    decor: [{ id: 1, is_completed: true, source: 'Craft' }],
    professions: [{ profession_id: 1, expansions: { 1: { completed: 5, total: 10, skill_points: 50, max_skill_points: 100 } } }],
};

const stubs = { LoadingSpinner: true, ScoreRadar: true, ShareScoreModal: true };

const mountPage = async () => {
    const wrapper = await mountWithPlugins(AccountScorePage, { stubs });
    return wrapper;
};

describe('AccountScorePage', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows loading spinner initially', async () => {
        axios.get.mockReturnValue(new Promise(() => {})); // never resolves

        const wrapper = await mountPage();

        expect(wrapper.findComponent({ name: 'LoadingSpinner' }).exists()).toBe(true);
    });

    it('transitions to computing state and shows progress', async () => {
        axios.get.mockResolvedValueOnce({
            data: { status: 'computing', progress: { loaded: 1, errors: 0, total: 3, current: 'CharName' } },
        });

        const wrapper = await mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('Analyse de CharName...');
        expect(wrapper.text()).toContain('1 / 3 personnages charges');
    });

    it('transitions to ready state with score data', async () => {
        axios.get.mockResolvedValueOnce({
            data: { status: 'ready', data: virtualProfile },
        });

        const wrapper = await mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('Score Compte');
        expect(wrapper.text()).toContain('3 personnages');
        expect(wrapper.findComponent({ name: 'ScoreRadar' }).exists()).toBe(true);
        expect(wrapper.text()).toContain('Detail par dimension');
    });

    it('shows error state on API error', async () => {
        axios.get.mockRejectedValueOnce({
            response: { status: 500, data: { message: 'Server error' } },
        });

        const wrapper = await mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('Server error');
        expect(wrapper.text()).toContain('Reessayer');
    });

    it('shows unauthenticated message on 401', async () => {
        axios.get.mockRejectedValueOnce({
            response: { status: 401 },
        });

        const wrapper = await mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('Aucun personnage trouve');
        expect(wrapper.text()).toContain('Connectez-vous avec Battle.net');
    });

    it('refresh button triggers score refresh', async () => {
        axios.get.mockResolvedValueOnce({
            data: { status: 'ready', data: virtualProfile },
        });

        const wrapper = await mountPage();
        await flushPromises();

        axios.post.mockResolvedValueOnce({});
        axios.get.mockReturnValueOnce(new Promise(() => {})); // polling restarts

        const refreshButton = wrapper.find('button');
        const refreshBtn = wrapper.findAll('button').find(b => b.text().includes('Recalculer'));
        expect(refreshBtn.exists()).toBe(true);

        await refreshBtn.trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/account/score/refresh');
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('cleans up polling on unmount', async () => {
        axios.get.mockResolvedValueOnce({
            data: { status: 'computing', progress: { loaded: 1, errors: 0, total: 3, current: 'CharName' } },
        });

        const wrapper = await mountPage();
        await flushPromises();

        const clearTimeoutSpy = vi.spyOn(global, 'clearTimeout');

        wrapper.unmount();

        expect(clearTimeoutSpy).toHaveBeenCalled();
        clearTimeoutSpy.mockRestore();
    });

    it('polls again after computing state with 2500ms delay', async () => {
        axios.get
            .mockResolvedValueOnce({
                data: { status: 'computing', progress: { loaded: 1, errors: 0, total: 3, current: 'CharName' } },
            })
            .mockResolvedValueOnce({
                data: { status: 'ready', data: virtualProfile },
            });

        const wrapper = await mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('Analyse de CharName...');
        expect(axios.get).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(2500);
        await flushPromises();

        expect(axios.get).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('Score Compte');
    });
});
