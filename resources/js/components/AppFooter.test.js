import { describe, it, expect } from 'vitest';
import AppFooter from './AppFooter.vue';
import { mountWithPlugins } from '../tests/helpers';

describe('AppFooter', () => {
    it('displays copyright with current year', async () => {
        const wrapper = await mountWithPlugins(AppFooter);
        const year = new Date().getFullYear();

        expect(wrapper.text()).toContain(`${year} WowPlanet`);
    });

    it('contains privacy and CGU links', async () => {
        const wrapper = await mountWithPlugins(AppFooter);

        const privacyLink = wrapper.find('a[href="/privacy"]');
        const cguLink = wrapper.find('a[href="/cgu"]');

        expect(privacyLink.exists()).toBe(true);
        expect(cguLink.exists()).toBe(true);
    });

    it('contains Discord link', async () => {
        const wrapper = await mountWithPlugins(AppFooter);
        const discordLink = wrapper.find('a[href="https://discord.gg/wa49gGF8cr"]');

        expect(discordLink.exists()).toBe(true);
        expect(discordLink.text()).toContain('Discord');
    });
});
