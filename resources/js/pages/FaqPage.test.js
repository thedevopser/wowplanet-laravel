import { describe, it, expect } from 'vitest';
import FaqPage from './FaqPage.vue';
import { mountWithPlugins } from '../tests/helpers';

describe('FaqPage', () => {
    it('renders the FAQ heading', async () => {
        const wrapper = await mountWithPlugins(FaqPage);

        expect(wrapper.find('h1').text()).toContain('Foire aux questions');
    });

    it('contains the five FAQ questions', async () => {
        const wrapper = await mountWithPlugins(FaqPage);
        const text = wrapper.text();

        expect(text).toContain('Qu\u2019est-ce que WowPlanet');
        expect(text).toContain('Comment importer mes personnages');
        expect(text).toContain('score compte');
        expect(text).toContain('sans se connecter');
        expect(text).toContain('t\u00e2ches');
    });

    it('contains a link to the database', async () => {
        const wrapper = await mountWithPlugins(FaqPage);
        const dbLink = wrapper.find('a[href="/base-de-donnees"]');

        expect(dbLink.exists()).toBe(true);
    });

    it('contains a link to Discord', async () => {
        const wrapper = await mountWithPlugins(FaqPage);
        const discordLink = wrapper.find('a[href="https://discord.gg/wa49gGF8cr"]');

        expect(discordLink.exists()).toBe(true);
    });
});
