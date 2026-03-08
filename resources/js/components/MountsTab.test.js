import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import MountsTab from './MountsTab.vue';

const makeCharacter = (mounts = [], mountsCount = null) => ({
    mountsCount: mountsCount ?? mounts.filter(m => m.is_completed).length,
    mounts,
});

/** Uncategorized mounts (no category/source) → "Non classé" flat list */
const sampleMounts = [
    { id: 1, name: 'Loup de guerre', is_completed: true, wowhead_id: 1001 },
    { id: 2, name: 'Destrier noir', is_completed: false, wowhead_id: 1002 },
    { id: 3, name: 'Aigle de guerre', is_completed: true, wowhead_id: 1003 },
];

/** Categorized mounts for categorized view tests */
const categorizedMounts = [
    { id: 1, name: 'Loup de guerre', is_completed: true, wowhead_id: 1001, icon_url: null, category: 'Classic', source: 'Reputation' },
    { id: 2, name: 'Destrier noir', is_completed: false, wowhead_id: 1002, icon_url: null, category: 'Classic', source: 'Reputation' },
    { id: 3, name: 'Aigle de guerre', is_completed: true, wowhead_id: 1003, icon_url: null, category: 'Classic', source: 'Achievement' },
];

describe('MountsTab', () => {
    it('renders the mounts heading and count', async () => {
        const wrapper = await mountWithPlugins(MountsTab, { props: { character: makeCharacter(categorizedMounts, 2) } });

        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('2 / 3');
    });

    it('displays mount names sorted alphabetically in uncategorized view', async () => {
        const wrapper = await mountWithPlugins(MountsTab, { props: { character: makeCharacter(sampleMounts) } });
        const mountNames = wrapper.findAll('.font-bold.text-slate-200').map(el => el.text());

        expect(mountNames[0]).toBe('Aigle de guerre');
        expect(mountNames[1]).toBe('Destrier noir');
        expect(mountNames[2]).toBe('Loup de guerre');
    });

    it('shows "Obtenue" badge for collected mounts', async () => {
        const wrapper = await mountWithPlugins(MountsTab, { props: { character: makeCharacter(sampleMounts) } });
        const badges = wrapper.findAll('.text-green-400');

        expect(badges.length).toBe(2);
    });

    it('displays mount IDs', async () => {
        const wrapper = await mountWithPlugins(MountsTab, { props: { character: makeCharacter(sampleMounts) } });

        expect(wrapper.text()).toContain('ID: 1');
        expect(wrapper.text()).toContain('ID: 2');
        expect(wrapper.text()).toContain('ID: 3');
    });

    it('links to wowhead', async () => {
        const wrapper = await mountWithPlugins(MountsTab, { props: { character: makeCharacter(sampleMounts) } });
        const links = wrapper.findAll('a[href*="wowhead.com"]');

        expect(links.length).toBe(3);
        expect(links[0].attributes('href')).toContain('/spell=');
    });

    it('handles empty mounts array', async () => {
        const wrapper = await mountWithPlugins(MountsTab, { props: { character: makeCharacter([], 0) } });

        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('Aucun résultat trouvé.');
    });

    it('paginates source cards at 8 per page', async () => {
        const sourceNames = [
            'Quest', 'Achievement', 'Vendor', 'Raid Drop', 'Dungeon Drop',
            'Reputation', 'Treasure', 'Rare Spawn', 'Renown', 'Daily Activities',
        ];
        const manyMounts = sourceNames.map((source, i) => ({
            id: i + 1,
            name: `Monture ${String(i + 1).padStart(2, '0')}`,
            is_completed: false,
            wowhead_id: i + 100,
            icon_url: null,
            category: 'The War Within',
            source,
        }));
        const wrapper = await mountWithPlugins(MountsTab, { props: { character: makeCharacter(manyMounts) } });

        expect(wrapper.text()).toContain('1 / 2');
        const sourceCards = wrapper.findAll('.bg-slate-800\\/40.border');
        expect(sourceCards.length).toBe(8);
    });
});
