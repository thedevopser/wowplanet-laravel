import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MountsTab from './MountsTab.vue';

const character = {
    mountsCount: 3,
    mounts: [
        { id: 1, name: 'Loup de guerre', is_completed: true, wowhead_id: 1001 },
        { id: 2, name: 'Destrier noir', is_completed: false, wowhead_id: 1002 },
        { id: 3, name: 'Aigle de guerre', is_completed: true, wowhead_id: 1003 },
    ],
};

describe('MountsTab', () => {
    it('renders the mounts heading', () => {
        const wrapper = mount(MountsTab, { props: { character } });

        expect(wrapper.text()).toContain('Montures');
    });

    it('displays mounts count', () => {
        const wrapper = mount(MountsTab, { props: { character } });

        expect(wrapper.text()).toContain('3');
        expect(wrapper.text()).toContain('/ 3 total');
    });

    it('displays mount names sorted alphabetically', () => {
        const wrapper = mount(MountsTab, { props: { character } });
        const mountNames = wrapper.findAll('.font-bold.text-slate-200').map(el => el.text());

        expect(mountNames[0]).toBe('Aigle de guerre');
        expect(mountNames[1]).toBe('Destrier noir');
        expect(mountNames[2]).toBe('Loup de guerre');
    });

    it('shows "Obtenue" badge for collected mounts', () => {
        const wrapper = mount(MountsTab, { props: { character } });
        const badges = wrapper.findAll('.text-green-400');

        expect(badges.length).toBe(2);
    });

    it('displays mount IDs', () => {
        const wrapper = mount(MountsTab, { props: { character } });

        expect(wrapper.text()).toContain('ID: 1');
        expect(wrapper.text()).toContain('ID: 2');
        expect(wrapper.text()).toContain('ID: 3');
    });

    it('links to wowhead', () => {
        const wrapper = mount(MountsTab, { props: { character } });
        const links = wrapper.findAll('a[href*="wowhead.com"]');

        expect(links.length).toBe(3);
        expect(links[0].attributes('href')).toContain('/spell=');
    });

    it('handles empty mounts array', () => {
        const wrapper = mount(MountsTab, { props: { character: { mountsCount: 0, mounts: [] } } });

        expect(wrapper.text()).toContain('0');
        expect(wrapper.text()).toContain('/ 0 total');
    });
});
