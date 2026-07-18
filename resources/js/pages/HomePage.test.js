import { describe, it, expect, beforeEach, vi } from 'vitest';

// État d'auth mutable exposé à travers le mock usePage.
const pageState = { url: '/', props: { auth: { isAuthenticated: false, isAdmin: false } } };

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => pageState,
    router: { visit: vi.fn(), on: vi.fn() },
}));

import HomePage from './HomePage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'WowPlanet - Suivi de progression World of Warcraft',
    description: 'Suivi de progression WoW',
    ogTitle: 'WowPlanet',
    ogDescription: 'Suivi de progression WoW',
    ogImage: 'https://example.com/og.png',
    ogUrl: 'https://example.com/',
    ogType: 'website',
    canonicalUrl: 'https://example.com/',
    jsonLd: null,
};

function mountHomePage() {
    return mountWithPlugins(HomePage, { props: { meta } });
}

describe('HomePage', () => {
    beforeEach(() => {
        localStorage.clear();
        pageState.props.auth = { isAuthenticated: false, isAdmin: false };
    });

    it('renders the hero section', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.text()).toContain('Suivez votre progression');
    });

    it('ne montre pas le flux de recherche manuelle dans le CTA', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.text()).not.toContain('Royaume');
        expect(wrapper.text()).not.toContain('Personnage');
    });

    it('shows Battle.net login CTA when not authenticated', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.text()).toContain('Se connecter avec Battle.net');
        expect(wrapper.text()).toContain('Commencer');
        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(true);
    });

    it('shows authenticated CTA when logged in', async () => {
        pageState.props.auth = { isAuthenticated: true, isAdmin: false };
        const wrapper = await mountHomePage();

        expect(wrapper.text()).toContain('Voir mes personnages');
        expect(wrapper.text()).toContain('Bienvenue');
        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(false);
    });

    it('renders the 4 feature cards', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.text()).toContain('Quêtes');
        expect(wrapper.text()).toContain('Hauts-faits');
        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('Mascottes');
    });

    it('renders data source info', async () => {
        const wrapper = await mountHomePage();

        expect(wrapper.text()).toContain('API officielle Blizzard');
    });

    it('affiche la carte d\'invitation Discord', async () => {
        const wrapper = await mountHomePage();
        expect(wrapper.find('a[href="https://discord.gg/wa49gGF8cr"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Rejoins la communauté');
    });

    it('affiche le composant DiscordBanner', async () => {
        const wrapper = await mountHomePage();
        expect(wrapper.findComponent({ name: 'DiscordBanner' }).exists()).toBe(true);
    });

    it('ajoute pb-14 quand la bannière est visible', async () => {
        const wrapper = await mountHomePage();
        expect(wrapper.find('[data-testid="homepage-root"]').classes()).toContain('pb-14');
    });

    it('n\'ajoute pas pb-14 quand la bannière est déjà dismissée', async () => {
        localStorage.setItem('discord_banner_dismissed', '1');
        const wrapper = await mountHomePage();
        expect(wrapper.find('[data-testid="homepage-root"]').classes()).not.toContain('pb-14');
    });
});
