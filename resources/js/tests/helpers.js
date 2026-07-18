import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import { vi } from 'vitest';

// vue-router a été retiré (app 100 % Inertia). Conservé en no-op pour la
// compatibilité des anciens appels `createMockRouter()` / options `router`.
export function createMockRouter() {
    return {};
}

export async function mountWithPlugins(component, options = {}) {
    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: options.initialState || {},
        stubActions: options.stubActions !== false,
    });

    return mount(component, {
        global: {
            plugins: [pinia],
            stubs: options.stubs || {},
            ...options.global,
        },
        props: options.props,
        slots: options.slots,
        attachTo: options.attachTo,
    });
}
