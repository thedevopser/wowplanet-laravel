import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ExpansionSelector from './ExpansionSelector.vue';

const expansions = [
    { id: 0, name: 'Classic' },
    { id: 1, name: 'The Burning Crusade' },
    { id: 10, name: 'The War Within' },
];

const collections = {
    0: { quests: { completed: 500, total: 1000 }, achievements: { completed: 100, total: 200 } },
    1: { quests: { completed: 200, total: 400 }, achievements: { completed: 50, total: 100 } },
    10: { quests: { completed: 10, total: 50 }, achievements: { completed: 5, total: 20 } },
};

describe('ExpansionSelector', () => {
    it('renders all expansion buttons', () => {
        const wrapper = mount(ExpansionSelector, {
            props: { expansions, activeExpansion: 0, collections, collectionType: 'quests' },
        });

        expect(wrapper.text()).toContain('Classic');
        expect(wrapper.text()).toContain('The Burning Crusade');
        expect(wrapper.text()).toContain('The War Within');
    });

    it('displays collection counters for each expansion', () => {
        const wrapper = mount(ExpansionSelector, {
            props: { expansions, activeExpansion: 0, collections, collectionType: 'quests' },
        });

        expect(wrapper.text()).toContain('500 / 1000');
        expect(wrapper.text()).toContain('200 / 400');
        expect(wrapper.text()).toContain('10 / 50');
    });

    it('emits update:activeExpansion on button click', async () => {
        const wrapper = mount(ExpansionSelector, {
            props: { expansions, activeExpansion: 0, collections, collectionType: 'quests' },
        });

        const buttons = wrapper.findAll('button');
        await buttons[2].trigger('click');

        expect(wrapper.emitted('update:activeExpansion')).toBeTruthy();
        expect(wrapper.emitted('update:activeExpansion')[0]).toEqual([10]);
    });

    it('highlights the active expansion button', () => {
        const wrapper = mount(ExpansionSelector, {
            props: { expansions, activeExpansion: 0, collections, collectionType: 'quests' },
        });

        const buttons = wrapper.findAll('button');
        expect(buttons[0].classes()).toContain('scale-105');
    });

    it('shows a fill-only bucket where it holds something', () => {
        const withBucket = [...expansions, { id: 99, name: 'Non classé', onlyWhenFilled: true }];
        const wrapper = mount(ExpansionSelector, {
            props: {
                expansions: withBucket,
                activeExpansion: 0,
                collections: { ...collections, 99: { quests: { completed: 0, total: 0 }, achievements: { completed: 12, total: 4566 } } },
                collectionType: 'achievements',
            },
        });

        expect(wrapper.text()).toContain('Non classé');
        expect(wrapper.text()).toContain('12 / 4566');
    });

    it('hides a fill-only bucket where it holds nothing', () => {
        const withBucket = [...expansions, { id: 99, name: 'Non classé', onlyWhenFilled: true }];
        const wrapper = mount(ExpansionSelector, {
            props: {
                expansions: withBucket,
                activeExpansion: 0,
                collections: { ...collections, 99: { quests: { completed: 0, total: 0 }, achievements: { completed: 12, total: 4566 } } },
                collectionType: 'quests',
            },
        });

        expect(wrapper.text()).not.toContain('Non classé');
        expect(wrapper.findAll('button')).toHaveLength(3);
    });

    it('hides a fill-only bucket the collections do not carry at all', () => {
        const withBucket = [...expansions, { id: 99, name: 'Non classé', onlyWhenFilled: true }];
        const wrapper = mount(ExpansionSelector, {
            props: { expansions: withBucket, activeExpansion: 0, collections, collectionType: 'achievements' },
        });

        expect(wrapper.text()).not.toContain('Non classé');
    });

    it('displays achievements counters when collectionType is achievements', () => {
        const wrapper = mount(ExpansionSelector, {
            props: { expansions, activeExpansion: 0, collections, collectionType: 'achievements', activeColor: 'amber' },
        });

        expect(wrapper.text()).toContain('100 / 200');
        expect(wrapper.text()).toContain('50 / 100');
    });
});
