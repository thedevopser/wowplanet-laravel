import { describe, it, expect } from 'vitest';
import BreadcrumbNav from './BreadcrumbNav.vue';
import { mountWithPlugins } from '../tests/helpers';

describe('BreadcrumbNav', () => {
    const crumbs = [
        { label: 'Base de données', to: '/base-de-donnees' },
        { label: 'Montures' },
    ];

    it('renders "WowPlanet" as root link to "/"', async () => {
        const wrapper = await mountWithPlugins(BreadcrumbNav, { props: { crumbs } });
        const rootLink = wrapper.find('a[href="/"]');

        expect(rootLink.exists()).toBe(true);
        expect(rootLink.text()).toBe('WowPlanet');
    });

    it('renders crumb labels', async () => {
        const wrapper = await mountWithPlugins(BreadcrumbNav, { props: { crumbs } });

        expect(wrapper.text()).toContain('Base de données');
        expect(wrapper.text()).toContain('Montures');
    });

    it('last crumb is a span with font-medium class', async () => {
        const wrapper = await mountWithPlugins(BreadcrumbNav, { props: { crumbs } });
        const spans = wrapper.findAll('span.font-medium');

        expect(spans.length).toBe(1);
        expect(spans[0].text()).toBe('Montures');
    });

    it('intermediate crumbs with "to" are router-links', async () => {
        const wrapper = await mountWithPlugins(BreadcrumbNav, { props: { crumbs } });
        const links = wrapper.findAll('a');

        // First link is WowPlanet ("/"), second is "Base de données" ("/base-de-donnees")
        expect(links.length).toBe(2);
        expect(links[1].attributes('href')).toBe('/base-de-donnees');
        expect(links[1].text()).toBe('Base de données');
    });

    it('renders separator "/" between crumbs', async () => {
        const wrapper = await mountWithPlugins(BreadcrumbNav, { props: { crumbs } });
        const separators = wrapper.findAll('span.text-slate-700');

        expect(separators.length).toBe(crumbs.length);
        separators.forEach((sep) => {
            expect(sep.text()).toBe('/');
        });
    });

    it('renders a single crumb as span (last item)', async () => {
        const singleCrumb = [{ label: 'Accueil', to: '/accueil' }];
        const wrapper = await mountWithPlugins(BreadcrumbNav, { props: { crumbs: singleCrumb } });

        // Even with a "to", the last crumb should be a span
        const spans = wrapper.findAll('span.font-medium');
        expect(spans.length).toBe(1);
        expect(spans[0].text()).toBe('Accueil');
    });
});
