import { describe, it, expect, vi } from 'vitest';
import { nextTick } from 'vue';
import { flushPromises } from '@vue/test-utils';
import { mountWithPlugins } from '../tests/helpers';
import AppHeader from './AppHeader.vue';
import { useCharacterStore } from '../stores/character';

describe('AppHeader', () => {
    it('renders the logo and title', async () => {
        const wrapper = await mountWithPlugins(AppHeader);

        expect(wrapper.text()).toContain('WowPlanet');
        expect(wrapper.find('img[alt="WowPlanet Logo"]').exists()).toBe(true);
    });

    it('shows login link when not authenticated', async () => {
        const wrapper = await mountWithPlugins(AppHeader, {
            initialState: { character: { isAuthenticated: false } },
        });

        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(true);
    });

    it('shows navigation links when authenticated', async () => {
        const wrapper = await mountWithPlugins(AppHeader, {
            initialState: { character: { isAuthenticated: true } },
        });

        expect(wrapper.text()).toContain('Mes personnages');
        expect(wrapper.text()).toContain('Mes classes');
    });

    it('does not show login link when authenticated', async () => {
        const wrapper = await mountWithPlugins(AppHeader, {
            initialState: { character: { isAuthenticated: true } },
        });

        expect(wrapper.find('a[href="/auth/blizzard/redirect"]').exists()).toBe(false);
    });

    it('renders search inputs', async () => {
        const wrapper = await mountWithPlugins(AppHeader);
        const inputs = wrapper.findAll('input[type="text"]');

        expect(inputs.length).toBeGreaterThanOrEqual(2);
    });

    it('has default realm set to Dalaran', async () => {
        const wrapper = await mountWithPlugins(AppHeader);
        const realmInput = wrapper.find('input[placeholder="Royaume"]');

        expect(realmInput.element.value).toBe('Dalaran');
    });

    it('navigates to character page on search', async () => {
        const wrapper = await mountWithPlugins(AppHeader);
        const router = wrapper.__router;

        const realmInputs = wrapper.findAll('input[placeholder="Royaume"]');
        const nameInputs = wrapper.findAll('input[placeholder*="Nom"]');

        await realmInputs[0].setValue('hyjal');
        await nameInputs[0].setValue('arthas');

        const searchBtn = wrapper.findAll('button').find(btn => btn.text().includes('Rechercher'));
        await searchBtn.trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('character');
        expect(router.currentRoute.value.params.realm).toBe('hyjal');
        expect(router.currentRoute.value.params.name).toBe('arthas');
    });

    it('calls logout on disconnect click', async () => {
        const wrapper = await mountWithPlugins(AppHeader, {
            initialState: { character: { isAuthenticated: true } },
        });
        const store = useCharacterStore();
        store.logout.mockResolvedValue();

        const logoutBtn = wrapper.findAll('button').find(btn => btn.text().includes('Déconnexion'));
        await logoutBtn.trigger('click');
        await nextTick();

        expect(store.logout).toHaveBeenCalled();
    });

    it('contient un lien Discord dans la nav desktop', async () => {
        const wrapper = await mountWithPlugins(AppHeader);
        const desktopNav = wrapper.find('.hidden.sm\\:flex');
        expect(desktopNav.find('a[href="https://discord.gg/wa49gGF8cr"]').exists()).toBe(true);
    });

    it('contient un lien Discord accessible sur mobile', async () => {
        const wrapper = await mountWithPlugins(AppHeader);
        const mobileDiscord = wrapper.find('a[href="https://discord.gg/wa49gGF8cr"][aria-label="Rejoindre le Discord WowPlanet"]');
        expect(mobileDiscord.exists()).toBe(true);
    });
});
