import { describe, it, expect, beforeEach } from 'vitest';
import { RouterLinkStub } from '@vue/test-utils';
import { mountWithPlugins } from '../tests/helpers';
import HomePage from './HomePage.vue';

describe('HomePage', () => {
    beforeEach(() => localStorage.clear());

    it('renders the hero section', async () => {
        const wrapper = await mountWithPlugins(HomePage);

        expect(wrapper.text()).toContain('Suivez votre progression');
    });

    it('ne montre pas le flux de recherche manuelle dans le CTA', async () => {
        const wrapper = await mountWithPlugins(HomePage, {
            initialState: { character: { isAuthenticated: false } },
        });

        expect(wrapper.text()).not.toContain('Royaume');
        expect(wrapper.text()).not.toContain('Personnage');
    });

    it('shows Battle.net login CTA when not authenticated', async () => {
        const wrapper = await mountWithPlugins(HomePage, {
            initialState: { character: { isAuthenticated: false } },
        });

        expect(wrapper.text()).toContain('Se connecter avec Battle.net');
        expect(wrapper.text()).toContain('Commencer');
        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(true);
    });

    it('shows authenticated CTA when logged in', async () => {
        const wrapper = await mountWithPlugins(HomePage, {
            initialState: { character: { isAuthenticated: true } },
            stubs: { RouterLink: RouterLinkStub },
        });

        expect(wrapper.text()).toContain('Voir mes personnages');
        expect(wrapper.text()).toContain('Bienvenue');
        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(false);
    });

    it('renders the 4 feature cards', async () => {
        const wrapper = await mountWithPlugins(HomePage);

        expect(wrapper.text()).toContain('Quêtes');
        expect(wrapper.text()).toContain('Hauts-faits');
        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('Mascottes');
    });

    it('renders data source info', async () => {
        const wrapper = await mountWithPlugins(HomePage);

        expect(wrapper.text()).toContain('API officielle Blizzard');
    });

    it('affiche la carte d\'invitation Discord', async () => {
        const wrapper = await mountWithPlugins(HomePage);
        expect(wrapper.find('a[href="https://discord.gg/wa49gGF8cr"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Rejoins la communauté');
    });

    it('affiche le composant DiscordBanner', async () => {
        localStorage.clear();
        const wrapper = await mountWithPlugins(HomePage);
        expect(wrapper.findComponent({ name: 'DiscordBanner' }).exists()).toBe(true);
    });

    it('ajoute pb-14 quand la bannière est visible', async () => {
        localStorage.clear();
        const wrapper = await mountWithPlugins(HomePage);
        expect(wrapper.find('[data-testid="homepage-root"]').classes()).toContain('pb-14');
    });

    it('n\'ajoute pas pb-14 quand la bannière est déjà dismissée', async () => {
        localStorage.setItem('discord_banner_dismissed', '1');
        const wrapper = await mountWithPlugins(HomePage);
        expect(wrapper.find('[data-testid="homepage-root"]').classes()).not.toContain('pb-14');
    });
});
