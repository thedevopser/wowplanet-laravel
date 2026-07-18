import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import axios from 'axios';
import { mountWithPlugins } from '../tests/helpers';
import AdminPage from './AdminPage.vue';

vi.mock('axios');

describe('AdminPage', () => {
    it('renders the admin panel and fetches status on mount', async () => {
        axios.get = vi.fn().mockResolvedValue({ data: { maintenance: false } });

        const wrapper = await mountWithPlugins(AdminPage);

        expect(wrapper.text()).toContain('Administration');
        expect(axios.get).toHaveBeenCalledWith('/api/admin/status');
    });
});
