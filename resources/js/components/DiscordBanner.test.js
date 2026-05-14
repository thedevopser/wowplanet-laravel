import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import DiscordBanner from './DiscordBanner.vue';

const DISCORD_URL = 'https://discord.gg/wa49gGF8cr';

describe('DiscordBanner', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('est visible quand discord_banner_dismissed est absent', () => {
        const wrapper = mount(DiscordBanner);
        expect(wrapper.find('[data-testid="discord-banner"]').exists()).toBe(true);
    });

    it('est masquée quand discord_banner_dismissed est présent', () => {
        localStorage.setItem('discord_banner_dismissed', '1');
        const wrapper = mount(DiscordBanner);
        expect(wrapper.find('[data-testid="discord-banner"]').exists()).toBe(false);
    });

    it('contient le lien Discord', () => {
        const wrapper = mount(DiscordBanner);
        expect(wrapper.find(`a[href="${DISCORD_URL}"]`).exists()).toBe(true);
    });

    it('ferme la bannière et enregistre dans localStorage au clic ✕', async () => {
        const wrapper = mount(DiscordBanner);
        await wrapper.find('[aria-label="Fermer la bannière Discord"]').trigger('click');
        expect(localStorage.getItem('discord_banner_dismissed')).toBe('1');
        await nextTick();
        expect(wrapper.find('[data-testid="discord-banner"]').exists()).toBe(false);
    });

    it('enregistre dans localStorage au clic sur Rejoindre', async () => {
        const wrapper = mount(DiscordBanner);
        await wrapper.find(`a[href="${DISCORD_URL}"]`).trigger('click');
        expect(localStorage.getItem('discord_banner_dismissed')).toBe('1');
    });

    it('émet dismissed au clic ✕', async () => {
        const wrapper = mount(DiscordBanner);
        await wrapper.find('[aria-label="Fermer la bannière Discord"]').trigger('click');
        expect(wrapper.emitted('dismissed')).toBeTruthy();
    });

    it('émet dismissed au clic sur Rejoindre', async () => {
        const wrapper = mount(DiscordBanner);
        await wrapper.find(`a[href="${DISCORD_URL}"]`).trigger('click');
        expect(wrapper.emitted('dismissed')).toBeTruthy();
    });
});
