import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import { createRouter, createMemoryHistory } from 'vue-router';
import { vi } from 'vitest';

const defaultRoutes = [
    { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
    { path: '/character/:realm/:name', name: 'character', component: { template: '<div>Character</div>' } },
    { path: '/my-characters', name: 'my-characters', component: { template: '<div>MyCharacters</div>' } },
    { path: '/class-stats', name: 'class-stats', component: { template: '<div>ClassStats</div>' } },
];

export function createMockRouter(options = {}) {
    return createRouter({
        history: createMemoryHistory(),
        routes: options.routes || defaultRoutes,
    });
}

export async function mountWithPlugins(component, options = {}) {
    const router = options.router || createMockRouter();

    router.push(options.initialRoute || '/');
    await router.isReady();

    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: options.initialState || {},
        stubActions: options.stubActions !== false,
    });

    const wrapper = mount(component, {
        global: {
            plugins: [pinia, router],
            stubs: options.stubs || {},
            ...options.global,
        },
        props: options.props,
        slots: options.slots,
        attachTo: options.attachTo,
    });

    wrapper.__router = router;

    return wrapper;
}
