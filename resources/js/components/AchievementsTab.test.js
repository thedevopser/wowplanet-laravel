import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import AchievementsTab from './AchievementsTab.vue';

const characterData = {
    name: 'Arthas',
    collections: {
        11: {
            quests: { completed: 25, total: 50 },
            achievements: {
                completed: 8,
                total: 20,
                categories: [
                    { name: 'Exploration', completed: 5, total: 10, items: [
                        { id: 101, name: 'Explorateur de Dornogal', is_completed: true },
                        { id: 102, name: 'Cartographe', is_completed: false },
                    ] },
                    { name: 'Donjons', completed: 3, total: 10, items: [
                        { id: 201, name: 'Premier donjon', is_completed: true },
                    ] },
                ],
            },
        },
    },
};

describe('AchievementsTab', () => {
    it('renders the achievements heading', async () => {
        const wrapper = await mountWithPlugins(AchievementsTab, {
            initialState: { character: { character: characterData } },
        });

        expect(wrapper.text()).toContain('Hauts-faits');
    });

    it('displays progress percentage', async () => {
        const wrapper = await mountWithPlugins(AchievementsTab, {
            initialState: { character: { character: characterData } },
        });

        expect(wrapper.text()).toContain('40%');
        expect(wrapper.text()).toContain('8 / 20');
    });

    it('displays categories sorted alphabetically', async () => {
        const wrapper = await mountWithPlugins(AchievementsTab, {
            initialState: { character: { character: characterData } },
        });

        const categoryNames = wrapper.findAll('.font-bold.text-slate-300').map(el => el.text());
        expect(categoryNames[0]).toBe('Donjons');
        expect(categoryNames[1]).toBe('Exploration');
    });

    it('displays category completion counters', async () => {
        const wrapper = await mountWithPlugins(AchievementsTab, {
            initialState: { character: { character: characterData } },
        });

        expect(wrapper.text()).toContain('5/10');
        expect(wrapper.text()).toContain('3/10');
    });

    it('expands category on click to show achievement items', async () => {
        const wrapper = await mountWithPlugins(AchievementsTab, {
            initialState: { character: { character: characterData } },
        });

        const categoryCards = wrapper.findAll('.cursor-pointer');
        await categoryCards[1].trigger('click');

        expect(wrapper.text()).toContain('Explorateur de Dornogal');
        expect(wrapper.text()).toContain('Cartographe');
    });

    it('collapses category on second click', async () => {
        const wrapper = await mountWithPlugins(AchievementsTab, {
            initialState: { character: { character: characterData } },
        });

        const categoryCards = wrapper.findAll('.cursor-pointer');
        await categoryCards[1].trigger('click');
        await categoryCards[1].trigger('click');

        expect(wrapper.text()).not.toContain('Explorateur de Dornogal');
    });
});
