import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import DiscordBanner from './DiscordBanner.vue';

const DISCORD_URL = 'https://discord.gg/wa49gGF8cr';

// La visibilité est décidée dans onMounted (SSR-safe) : on attend un tick après
// le montage pour laisser la bannière apparaître avant de l'inspecter.
async function mountBanner() {
    const wrapper = mount(DiscordBanner);
    await nextTick();
    return wrapper;
}

describe('DiscordBanner', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('est visible quand discord_banner_dismissed est absent', async () => {
        const wrapper = await mountBanner();
        expect(wrapper.find('[data-testid="discord-banner"]').exists()).toBe(true);
    });

    it('est masquée quand discord_banner_dismissed est présent', async () => {
        localStorage.setItem('discord_banner_dismissed', '1');
        const wrapper = await mountBanner();
        expect(wrapper.find('[data-testid="discord-banner"]').exists()).toBe(false);
    });

    it('contient le lien Discord', async () => {
        const wrapper = await mountBanner();
        expect(wrapper.find(`a[href="${DISCORD_URL}"]`).exists()).toBe(true);
    });

    it('ferme la bannière et enregistre dans localStorage au clic ✕', async () => {
        const wrapper = await mountBanner();
        await wrapper.find('[aria-label="Fermer la bannière Discord"]').trigger('click');
        expect(localStorage.getItem('discord_banner_dismissed')).toBe('1');
        await nextTick();
        expect(wrapper.find('[data-testid="discord-banner"]').exists()).toBe(false);
    });

    it('enregistre dans localStorage et masque la bannière au clic sur Rejoindre', async () => {
        const wrapper = await mountBanner();
        await wrapper.find(`a[href="${DISCORD_URL}"]`).trigger('click');
        expect(localStorage.getItem('discord_banner_dismissed')).toBe('1');
        await nextTick();
        expect(wrapper.find('[data-testid="discord-banner"]').exists()).toBe(false);
    });

    it('émet dismissed au clic ✕', async () => {
        const wrapper = await mountBanner();
        await wrapper.find('[aria-label="Fermer la bannière Discord"]').trigger('click');
        expect(wrapper.emitted('dismissed')).toBeTruthy();
    });

    it('émet dismissed au clic sur Rejoindre', async () => {
        const wrapper = await mountBanner();
        await wrapper.find(`a[href="${DISCORD_URL}"]`).trigger('click');
        expect(wrapper.emitted('dismissed')).toBeTruthy();
    });
});
