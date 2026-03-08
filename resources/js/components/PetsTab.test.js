import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import PetsTab from './PetsTab.vue';

const makeCharacter = (pets = [], petsCount = null) => ({
    petsCount: petsCount ?? pets.filter(p => p.is_completed).length,
    pets,
});

const samplePets = [
    { id: 1, name: 'Tigre spectral', is_completed: true, wowhead_id: 2001 },
    { id: 2, name: 'Bébé murloc', is_completed: false, wowhead_id: 2002 },
    { id: 3, name: 'Arcaniste', is_completed: true, wowhead_id: 2003 },
];

/** Sample pets with category/source for categorized view tests */
const categorizedPets = [
    { id: 1, name: 'Tigre spectral', is_completed: true, wowhead_id: 2001, icon_url: null, category: 'Classic', source: 'Drop' },
    { id: 2, name: 'Bébé murloc', is_completed: false, wowhead_id: 2002, icon_url: null, category: 'Classic', source: 'Quest' },
    { id: 3, name: 'Arcaniste', is_completed: true, wowhead_id: 2003, icon_url: null, category: 'Classic', source: 'Drop' },
];

describe('PetsTab', () => {
    it('renders the pets heading', async () => {
        const wrapper = await mountWithPlugins(PetsTab, { props: { character: makeCharacter(categorizedPets, 2) } });

        expect(wrapper.text()).toContain('Mascottes');
    });

    it('displays pets count', async () => {
        const wrapper = await mountWithPlugins(PetsTab, { props: { character: makeCharacter(categorizedPets, 2) } });

        expect(wrapper.text()).toContain('2 / 3');
    });

    it('displays pet names sorted alphabetically', async () => {
        const wrapper = await mountWithPlugins(PetsTab, { props: { character: makeCharacter(samplePets) } });
        // Uncategorized items are rendered as flat list sorted alphabetically
        const names = wrapper.findAll('.font-bold.text-slate-200').map(el => el.text());

        expect(names[0]).toBe('Arcaniste');
        expect(names[1]).toBe('Bébé murloc');
        expect(names[2]).toBe('Tigre spectral');
    });

    it('shows "Obtenue" badge for collected pets', async () => {
        const wrapper = await mountWithPlugins(PetsTab, { props: { character: makeCharacter(samplePets) } });
        const badges = wrapper.findAll('.text-green-400');

        expect(badges.length).toBe(2);
    });

    it('displays pet IDs', async () => {
        const wrapper = await mountWithPlugins(PetsTab, { props: { character: makeCharacter(samplePets) } });

        expect(wrapper.text()).toContain('ID: 1');
        expect(wrapper.text()).toContain('ID: 2');
        expect(wrapper.text()).toContain('ID: 3');
    });

    it('links to wowhead', async () => {
        const wrapper = await mountWithPlugins(PetsTab, { props: { character: makeCharacter(samplePets) } });
        const links = wrapper.findAll('a[href*="wowhead.com"]');

        expect(links.length).toBe(3);
        expect(links[0].attributes('href')).toContain('/npc=');
    });

    it('handles empty pets array', async () => {
        const wrapper = await mountWithPlugins(PetsTab, { props: { character: makeCharacter([], 0) } });

        expect(wrapper.text()).toContain('Aucun résultat trouvé.');
    });
});
