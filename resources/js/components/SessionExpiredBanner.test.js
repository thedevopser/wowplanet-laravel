import { vi, describe, it, expect, beforeEach } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import SessionExpiredBanner from './SessionExpiredBanner.vue';

describe('SessionExpiredBanner', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete window.location;
        window.location = { href: '' };
    });

    it('n\'est pas visible quand sessionExpired est false', async () => {
        const wrapper = await mountWithPlugins(SessionExpiredBanner, {
            initialState: { character: { sessionExpired: false } },
        });

        expect(wrapper.find('[data-testid="session-expired-banner"]').exists()).toBe(false);
    });

    it('est visible quand sessionExpired est true', async () => {
        const wrapper = await mountWithPlugins(SessionExpiredBanner, {
            initialState: { character: { sessionExpired: true } },
        });

        expect(wrapper.find('[data-testid="session-expired-banner"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('session a expiré');
    });

    it('redirige vers /auth/blizzard/redirect au clic sur "Se reconnecter"', async () => {
        const wrapper = await mountWithPlugins(SessionExpiredBanner, {
            initialState: { character: { sessionExpired: true } },
        });

        await wrapper.find('[data-testid="reconnect-btn"]').trigger('click');

        expect(window.location.href).toBe('/auth/blizzard/redirect');
    });

    it('appelle clearSessionExpired au clic sur le bouton fermer', async () => {
        const wrapper = await mountWithPlugins(SessionExpiredBanner, {
            initialState: { character: { sessionExpired: true } },
            stubActions: false,
        });

        await wrapper.find('[data-testid="dismiss-btn"]').trigger('click');

        const store = wrapper.vm.store ?? wrapper.vm.$pinia._s.get('character');
        expect(store.sessionExpired).toBe(false);
    });
});
