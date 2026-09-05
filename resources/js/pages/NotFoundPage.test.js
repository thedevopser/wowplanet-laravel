import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/inconnu', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import { mountWithPlugins } from '../tests/helpers';
import AppLayout from '../layouts/AppLayout.vue';
import NotFoundPage from './NotFoundPage.vue';

describe('NotFoundPage', () => {
    it('renders the error code and an explanation', async () => {
        const wrapper = await mountWithPlugins(NotFoundPage);

        expect(wrapper.text()).toContain('404');
        expect(wrapper.text()).toContain('Page introuvable');
    });

    it('offers a way back to the home page', async () => {
        const wrapper = await mountWithPlugins(NotFoundPage);

        expect(wrapper.find('a').attributes('href')).toBe('/');
    });

    it('is rendered inside the application layout', () => {
        expect(NotFoundPage.layout).toBe(AppLayout);
    });
});
