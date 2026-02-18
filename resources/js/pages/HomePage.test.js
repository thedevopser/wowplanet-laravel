import { describe, it, expect } from 'vitest';
import { RouterLinkStub } from '@vue/test-utils';
import { mountWithPlugins } from '../tests/helpers';
import HomePage from './HomePage.vue';

describe('HomePage', () => {
    it('renders the hero section', async () => {
        const wrapper = await mountWithPlugins(HomePage);

        expect(wrapper.text()).toContain('Suivez votre progression');
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
});
