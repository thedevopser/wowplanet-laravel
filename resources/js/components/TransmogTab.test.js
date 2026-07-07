import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import TransmogTab from './TransmogTab.vue';

function makeCharacter(appearances = [], appearancesCount = 0) {
    return { appearancesCount, appearances };
}

describe('TransmogTab', () => {
    it('renders the global completion percentage', async () => {
        const wrapper = await mountWithPlugins(TransmogTab, {
            props: {
                character: makeCharacter([
                    { slot: 'HEAD', category: 'Armure', total: 10, completed: 5 },
                    { slot: 'WEAPON', category: 'Arme', total: 10, completed: 0 },
                ]),
            },
        });

        // 5 / 20 = 25%
        expect(wrapper.text()).toContain('25');
        expect(wrapper.text()).toContain('5');
    });

    it('renders one row per slot with its counters and translated name', async () => {
        const wrapper = await mountWithPlugins(TransmogTab, {
            props: {
                character: makeCharacter([
                    { slot: 'HEAD', category: 'Armure', total: 8, completed: 3 },
                    { slot: 'WEAPON', category: 'Arme', total: 4, completed: 4 },
                ]),
            },
        });

        expect(wrapper.text()).toContain('Tête');
        expect(wrapper.text()).toContain('3');
        expect(wrapper.text()).toContain('8');
        expect(wrapper.text()).toContain('Arme');
    });

    it('shows an empty state when no appearances are present', async () => {
        const wrapper = await mountWithPlugins(TransmogTab, {
            props: { character: makeCharacter([], 0) },
        });

        expect(wrapper.text()).toMatch(/Aucune|aucune/);
    });

    it('filters slots by category', async () => {
        const wrapper = await mountWithPlugins(TransmogTab, {
            props: {
                character: makeCharacter([
                    { slot: 'HEAD', category: 'Armure', total: 8, completed: 3 },
                    { slot: 'WEAPON', category: 'Arme', total: 4, completed: 4 },
                ]),
            },
        });

        wrapper.vm.activeCategory = 'Arme';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Arme');
        expect(wrapper.text()).not.toContain('Tête');
    });
});
