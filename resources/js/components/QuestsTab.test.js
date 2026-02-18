import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import QuestsTab from './QuestsTab.vue';
import { useCharacterStore } from '../stores/character';

const characterData = {
    name: 'Arthas',
    collections: {
        10: {
            quests: {
                completed: 25,
                total: 50,
                zones: [
                    { name: 'Dornogal', completed: 10, total: 20, items: [
                        { id: 1, name: 'Quête A', is_completed: true },
                        { id: 2, name: 'Quête B', is_completed: false },
                    ] },
                    { name: 'Isle of Dorn', completed: 15, total: 30, items: [
                        { id: 3, name: 'Quête C', is_completed: true },
                    ] },
                ],
            },
            achievements: { completed: 5, total: 20 },
        },
        0: {
            quests: { completed: 500, total: 1000, zones: [] },
            achievements: { completed: 100, total: 200 },
        },
    },
};

describe('QuestsTab', () => {
    it('renders the quests heading', async () => {
        const wrapper = await mountWithPlugins(QuestsTab, {
            initialState: { character: { character: characterData } },
        });

        expect(wrapper.text()).toContain('Quêtes');
    });

    it('displays progress percentage', async () => {
        const wrapper = await mountWithPlugins(QuestsTab, {
            initialState: { character: { character: characterData } },
        });

        expect(wrapper.text()).toContain('50%');
        expect(wrapper.text()).toContain('25 / 50');
    });

    it('displays zones sorted alphabetically', async () => {
        const wrapper = await mountWithPlugins(QuestsTab, {
            initialState: { character: { character: characterData } },
        });

        const zoneNames = wrapper.findAll('.font-bold.text-slate-300').map(el => el.text());
        expect(zoneNames).toContain('Dornogal');
        expect(zoneNames).toContain('Isle of Dorn');
    });

    it('displays zone completion counters', async () => {
        const wrapper = await mountWithPlugins(QuestsTab, {
            initialState: { character: { character: characterData } },
        });

        expect(wrapper.text()).toContain('10/20');
        expect(wrapper.text()).toContain('15/30');
    });

    it('expands zone on click to show quest items', async () => {
        const wrapper = await mountWithPlugins(QuestsTab, {
            initialState: { character: { character: characterData } },
        });

        const zoneCards = wrapper.findAll('.cursor-pointer');
        await zoneCards[0].trigger('click');

        expect(wrapper.text()).toContain('Quête A');
        expect(wrapper.text()).toContain('Quête B');
    });

    it('collapses zone on second click', async () => {
        const wrapper = await mountWithPlugins(QuestsTab, {
            initialState: { character: { character: characterData } },
        });

        const zoneCards = wrapper.findAll('.cursor-pointer');
        await zoneCards[0].trigger('click');
        await zoneCards[0].trigger('click');

        expect(wrapper.text()).not.toContain('Quête A');
    });
});
