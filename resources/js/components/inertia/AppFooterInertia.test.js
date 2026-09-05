import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AppFooterInertia from './AppFooterInertia.vue';

describe('AppFooterInertia', () => {
    it('states the site is unaffiliated with Blizzard', () => {
        const wrapper = mount(AppFooterInertia);

        expect(wrapper.text()).toContain('Site fan non officiel, sans lien ni affiliation avec Blizzard Entertainment.');
    });

    it('shows the current year in the copyright notice', () => {
        const wrapper = mount(AppFooterInertia);

        expect(wrapper.text()).toContain(String(new Date().getFullYear()));
    });

    it('links to the legal and help pages', () => {
        const wrapper = mount(AppFooterInertia);

        const hrefs = wrapper.findAll('a').map(a => a.attributes('href'));

        expect(hrefs).toEqual(expect.arrayContaining(['/addons', '/privacy', '/cgu', '/faq']));
    });

    it('opens the Discord invitation in a safe new tab', () => {
        const wrapper = mount(AppFooterInertia);

        const discord = wrapper.findAll('a').find(a => a.attributes('href').includes('discord.gg'));

        expect(discord.attributes('target')).toBe('_blank');
        expect(discord.attributes('rel')).toBe('noopener noreferrer');
    });
});
