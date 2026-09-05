import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/', props: {} });

    return {
        __page: page,
        Head: { name: 'Head', render: () => null },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
        usePage: () => page,
        router: { visit: vi.fn(), on: vi.fn(() => () => {}) },
    };
});

import { __page } from '@inertiajs/vue3';
import { useCharacterStore } from '../stores/character';
import { useTaskStore } from '../stores/tasks';
import { mountWithPlugins } from '../tests/helpers';
import AppLayout from './AppLayout.vue';

const stubs = {
    AppHeaderInertia: true,
    AppFooterInertia: true,
    TaskSidebarInertia: true,
    SessionExpiredBanner: true,
    AuthRequiredBanner: true,
};

const mountLayout = (options = {}) => mountWithPlugins(AppLayout, {
    stubs,
    slots: { default: '<p>Contenu de la page</p>' },
    ...options,
});

beforeEach(() => {
    __page.url = '/';
    document.documentElement.classList.remove('dark');
});

describe('AppLayout', () => {
    it('renders the page content inside the shell', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.text()).toContain('Contenu de la page');
        expect(wrapper.findComponent({ name: 'AppHeaderInertia' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'AppFooterInertia' }).exists()).toBe(true);
    });

    it('offers a skip link to the main content', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.find('a[href="#main-content"]').text()).toBe('Aller au contenu principal');
        expect(wrapper.find('#main-content').exists()).toBe(true);
    });

    it('checks the authentication state on mount', async () => {
        const wrapper = await mountLayout();
        const store = useCharacterStore(wrapper.vm.$pinia);

        expect(store.checkAuth).toHaveBeenCalled();
    });

    it('applies the dark theme on mount', async () => {
        await mountLayout({ initialState: { character: { theme: 'dark' } } });

        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('leaves the light theme off the document on mount', async () => {
        await mountLayout({ initialState: { character: { theme: 'light' } } });

        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('follows a theme change', async () => {
        const wrapper = await mountLayout({ initialState: { character: { theme: 'dark' } } });
        const store = useCharacterStore(wrapper.vm.$pinia);

        store.theme = 'light';
        await wrapper.vm.$nextTick();

        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('fetches the tasks once the visitor becomes authenticated', async () => {
        const wrapper = await mountLayout();
        const store = useCharacterStore(wrapper.vm.$pinia);
        const taskStore = useTaskStore(wrapper.vm.$pinia);

        expect(taskStore.fetchTasks).not.toHaveBeenCalled();

        store.isAuthenticated = true;
        await wrapper.vm.$nextTick();

        expect(taskStore.fetchTasks).toHaveBeenCalled();
    });

    it('does not fetch the tasks when the visitor logs out', async () => {
        const wrapper = await mountLayout({ initialState: { character: { isAuthenticated: true } } });
        const store = useCharacterStore(wrapper.vm.$pinia);
        const taskStore = useTaskStore(wrapper.vm.$pinia);

        store.isAuthenticated = false;
        await wrapper.vm.$nextTick();

        expect(taskStore.fetchTasks).not.toHaveBeenCalled();
    });

    it('shows the task sidebar only to an authenticated visitor', async () => {
        const anonymous = await mountLayout();
        const authenticated = await mountLayout({ initialState: { character: { isAuthenticated: true } } });

        expect(anonymous.findComponent({ name: 'TaskSidebarInertia' }).exists()).toBe(false);
        expect(authenticated.findComponent({ name: 'TaskSidebarInertia' }).exists()).toBe(true);
    });

    it('displays the store error as an alert', async () => {
        const wrapper = await mountLayout({ initialState: { character: { error: 'Personnage introuvable' } } });

        expect(wrapper.find('[role="alert"]').text()).toBe('Personnage introuvable');
    });

    it('shows no alert without an error', async () => {
        const wrapper = await mountLayout();

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('drops the centered container on the database pages', async () => {
        __page.url = '/base-de-donnees/montures?page=2';

        const wrapper = await mountLayout();

        expect(wrapper.find('.max-w-360').exists()).toBe(false);
        expect(wrapper.find('#main-content').classes()).toContain('flex');
    });

    it('keeps the centered container on the other pages', async () => {
        __page.url = '/my-characters';

        const wrapper = await mountLayout();

        expect(wrapper.find('.max-w-360').exists()).toBe(true);
        expect(wrapper.find('#main-content').classes()).not.toContain('flex');
    });
});
