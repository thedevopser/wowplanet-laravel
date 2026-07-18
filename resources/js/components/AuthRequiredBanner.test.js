import { describe, it, expect, afterEach, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import AuthRequiredBanner from './AuthRequiredBanner.vue';

// Remplace window.location par un objet contrôlé (pattern déjà utilisé dans
// SessionExpiredBanner.test.js) pour piloter search/pathname sans les quirks jsdom.
function mockLocation({ search = '', pathname = '/', hash = '' } = {}) {
    delete window.location;
    window.location = { href: pathname + search + hash, search, pathname, hash };
}

// visible passe à true dans onMounted : on attend le flush réactif + l'insertion
// de la <Transition> avant d'inspecter le DOM.
async function mountBanner() {
    const wrapper = mount(AuthRequiredBanner);
    await nextTick();
    await nextTick();
    return wrapper;
}

describe('AuthRequiredBanner', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('stays hidden when the auth marker is absent', async () => {
        mockLocation({ search: '' });
        const wrapper = await mountBanner();

        expect(wrapper.find('[data-testid="auth-required-banner"]').exists()).toBe(false);
    });

    it('shows the banner and strips the marker from the URL when auth=required', async () => {
        mockLocation({ search: '?auth=required&keep=1' });
        const spy = vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
        const wrapper = await mountBanner();

        expect(wrapper.find('[data-testid="auth-required-banner"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('connexion Battle.net');

        expect(spy).toHaveBeenCalledOnce();
        const newUrl = spy.mock.calls[0][2];
        expect(newUrl).not.toContain('auth=required');
        expect(newUrl).toContain('keep=1');
    });

    it('redirects to Battle.net login on connect click', async () => {
        mockLocation({ search: '?auth=required' });
        vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
        const wrapper = await mountBanner();

        await wrapper.find('[data-testid="auth-required-connect-btn"]').trigger('click');

        expect(window.location.href).toBe('/auth/blizzard/redirect');
    });

    it('dismisses the banner on close click', async () => {
        mockLocation({ search: '?auth=required' });
        vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
        const wrapper = await mountBanner();

        await wrapper.find('[data-testid="auth-required-dismiss-btn"]').trigger('click');

        expect(wrapper.find('[data-testid="auth-required-banner"]').exists()).toBe(false);
    });
});
