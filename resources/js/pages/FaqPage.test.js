import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/faq', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import FaqPage from './FaqPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'FAQ - Questions fréquentes | WowPlanet',
    description: 'FAQ WowPlanet',
    ogTitle: 'FAQ',
    ogDescription: 'FAQ WowPlanet',
    ogImage: 'https://example.com/og.png',
    ogUrl: 'https://example.com/faq',
    ogType: 'website',
    canonicalUrl: 'https://example.com/faq',
    jsonLd: null,
};

function mountFaqPage() {
    return mountWithPlugins(FaqPage, { props: { meta } });
}

describe('FaqPage', () => {
    it('renders the FAQ heading', async () => {
        const wrapper = await mountFaqPage();

        expect(wrapper.find('h1').text()).toContain('Foire aux questions');
    });

    it('contains the five FAQ questions', async () => {
        const wrapper = await mountFaqPage();
        const text = wrapper.text();

        expect(text).toContain('Qu’est-ce que WowPlanet');
        expect(text).toContain('Comment importer mes personnages');
        expect(text).toContain('score compte');
        expect(text).toContain('sans se connecter');
        expect(text).toContain('tâches');
    });

    it('contains a link to the database', async () => {
        const wrapper = await mountFaqPage();
        const dbLink = wrapper.find('a[href="/base-de-donnees"]');

        expect(dbLink.exists()).toBe(true);
    });

    it('contains a link to Discord', async () => {
        const wrapper = await mountFaqPage();
        const discordLink = wrapper.find('a[href="https://discord.gg/wa49gGF8cr"]');

        expect(discordLink.exists()).toBe(true);
    });
});
