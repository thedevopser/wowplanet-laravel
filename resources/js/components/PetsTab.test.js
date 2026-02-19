import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PetsTab from './PetsTab.vue';

const character = {
    petsCount: 3,
    pets: [
        { id: 1, name: 'Tigre spectral', is_completed: true, wowhead_id: 2001 },
        { id: 2, name: 'Bébé murloc', is_completed: false, wowhead_id: 2002 },
        { id: 3, name: 'Arcaniste', is_completed: true, wowhead_id: 2003 },
    ],
};

describe('PetsTab', () => {
    it('renders the pets heading', () => {
        const wrapper = mount(PetsTab, { props: { character } });

        expect(wrapper.text()).toContain('Mascottes');
    });

    it('displays pets count', () => {
        const wrapper = mount(PetsTab, { props: { character } });

        expect(wrapper.text()).toContain('3');
        expect(wrapper.text()).toContain('/ 3 total');
    });

    it('displays pet names sorted alphabetically', () => {
        const wrapper = mount(PetsTab, { props: { character } });
        const petNames = wrapper.findAll('.font-bold.text-slate-200').map(el => el.text());

        expect(petNames[0]).toBe('Arcaniste');
        expect(petNames[1]).toBe('Bébé murloc');
        expect(petNames[2]).toBe('Tigre spectral');
    });

    it('shows "Obtenue" badge for collected pets', () => {
        const wrapper = mount(PetsTab, { props: { character } });
        const badges = wrapper.findAll('.text-green-400');

        expect(badges.length).toBe(2);
    });

    it('displays pet IDs', () => {
        const wrapper = mount(PetsTab, { props: { character } });

        expect(wrapper.text()).toContain('ID: 1');
        expect(wrapper.text()).toContain('ID: 2');
        expect(wrapper.text()).toContain('ID: 3');
    });

    it('links to wowhead', () => {
        const wrapper = mount(PetsTab, { props: { character } });
        const links = wrapper.findAll('a[href*="wowhead.com"]');

        expect(links.length).toBe(3);
        expect(links[0].attributes('href')).toContain('/npc=');
    });

    it('handles empty pets array', () => {
        const wrapper = mount(PetsTab, { props: { character: { petsCount: 0, pets: [] } } });

        expect(wrapper.text()).toContain('0');
        expect(wrapper.text()).toContain('/ 0 total');
    });
});
