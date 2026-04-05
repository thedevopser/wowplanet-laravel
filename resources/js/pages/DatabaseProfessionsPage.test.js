import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mountWithPlugins, createMockRouter } from '../tests/helpers';
import DatabaseProfessionsPage from './DatabaseProfessionsPage.vue';
import axios from 'axios';

vi.mock('axios');

const mockProfessionList = {
    professions: [
        { id: 1, slug: 'forge', name_fr: 'Forge', type: 'primary', recipe_count: 800 },
        { id: 2, slug: 'alchimie', name_fr: 'Alchimie', type: 'primary', recipe_count: 600 },
        { id: 3, slug: 'cuisine', name_fr: 'Cuisine', type: 'secondary', recipe_count: 400 },
    ],
    total_recipes: 15000,
};

const mockRecipeData = {
    items: [
        { id: 10, name_fr: 'Épée en acier', category_name: 'Armes', wowhead_spell_id: 500, faction: null },
        { id: 11, name_fr: 'Bouclier lourd', category_name: 'Armes', wowhead_spell_id: 501, faction: null },
        { id: 12, name_fr: 'Plastron en fer', category_name: 'Armures', wowhead_spell_id: 502, faction: 'Alliance' },
    ],
    expansions: [
        { slug: 'classic', name: 'Classic', count: 200 },
        { slug: 'the-war-within', name: 'The War Within', count: 100 },
    ],
    profession: { name_fr: 'Forge' },
    total: 3,
    current_page: 1,
    last_page: 1,
    per_page: 50,
};

const routes = [
    { path: '/base-de-donnees/professions/:profession?', component: DatabaseProfessionsPage },
    { path: '/base-de-donnees', component: { template: '<div/>' } },
];

describe('DatabaseProfessionsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders heading "Professions" in list mode', async () => {
        axios.get.mockResolvedValue({ data: mockProfessionList });

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseProfessionsPage, {
            router,
            initialRoute: '/base-de-donnees/professions',
            stubs: { BreadcrumbNav: true, SearchFilter: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Professions');
    });

    it('fetches profession list and displays primary/secondary', async () => {
        axios.get.mockResolvedValue({ data: mockProfessionList });

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseProfessionsPage, {
            router,
            initialRoute: '/base-de-donnees/professions',
            stubs: { BreadcrumbNav: true, SearchFilter: true },
        });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/professions');
        expect(wrapper.text()).toContain('Professions principales');
        expect(wrapper.text()).toContain('Forge');
        expect(wrapper.text()).toContain('Alchimie');
        expect(wrapper.text()).toContain('Professions secondaires');
        expect(wrapper.text()).toContain('Cuisine');
    });

    it('renders recipe view when profession param is set', async () => {
        axios.get.mockResolvedValue({ data: mockRecipeData });

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseProfessionsPage, {
            router,
            initialRoute: '/base-de-donnees/professions/forge',
            stubs: { BreadcrumbNav: true, SearchFilter: true },
        });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/professions/recipes', {
            params: { profession: 'forge', page: 1 },
        });
        expect(wrapper.text()).toContain('Forge');
        expect(wrapper.text()).toContain('Armes');
        expect(wrapper.text()).toContain('Armures');
    });

    it('handles API error', async () => {
        axios.get.mockRejectedValue(new Error('Network error'));

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseProfessionsPage, {
            router,
            initialRoute: '/base-de-donnees/professions',
            stubs: { BreadcrumbNav: true, SearchFilter: true },
        });
        await flushPromises();

        expect(wrapper.text()).not.toContain('Forge');
    });
});
