import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BreadcrumbNavInertia from './BreadcrumbNavInertia.vue';

const mountCrumbs = crumbs => mount(BreadcrumbNavInertia, { props: { crumbs } });

describe('BreadcrumbNavInertia', () => {
    it('always starts from the home page', () => {
        const wrapper = mountCrumbs([]);

        expect(wrapper.find('a').attributes('href')).toBe('/');
        expect(wrapper.find('a').text()).toBe('WowPlanet');
    });

    it('links every crumb but the last one', () => {
        const wrapper = mountCrumbs([
            { label: 'Base de données', to: '/base-de-donnees' },
            { label: 'Montures', to: '/base-de-donnees/montures' },
        ]);

        const hrefs = wrapper.findAll('a').map(a => a.attributes('href'));

        expect(hrefs).toEqual(['/', '/base-de-donnees']);
    });

    it('renders the last crumb as plain text', () => {
        const wrapper = mountCrumbs([
            { label: 'Base de données', to: '/base-de-donnees' },
            { label: 'Montures', to: '/base-de-donnees/montures' },
        ]);

        const last = wrapper.findAll('span').at(-1);

        expect(last.text()).toBe('Montures');
        expect(last.classes()).toContain('font-medium');
    });

    it('renders a crumb without destination as plain text', () => {
        const wrapper = mountCrumbs([
            { label: 'Base de données' },
            { label: 'Montures', to: '/base-de-donnees/montures' },
        ]);

        expect(wrapper.findAll('a').map(a => a.attributes('href'))).toEqual(['/']);
        expect(wrapper.text()).toContain('Base de données');
    });

    it('separates the crumbs with a slash', () => {
        const wrapper = mountCrumbs([{ label: 'FAQ', to: '/faq' }]);

        expect(wrapper.findAll('span').filter(s => s.text() === '/')).toHaveLength(1);
    });
});
