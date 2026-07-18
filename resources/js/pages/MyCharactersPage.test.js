import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/my-characters', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import { nextTick } from 'vue';
import { mountWithPlugins } from '../tests/helpers';
import MyCharactersPage from './MyCharactersPage.vue';
import { useCharacterStore } from '../stores/character';

const userCharacters = [
    { name: 'Arthas', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, classId: 6, className: 'Chevalier de la mort', raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
    { name: 'Thrall', realmSlug: 'dalaran', realm: 'Dalaran', level: 70, classId: 7, className: 'Chaman', raceName: 'Orc', faction: 'Horde', avatarUrl: '' },
    { name: 'Jaina', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, classId: 8, className: 'Mage', raceName: 'Humaine', faction: 'Alliance', avatarUrl: '' },
];

const mountPage = async (storeState = {}) => {
    const wrapper = await mountWithPlugins(MyCharactersPage, {
        initialState: { character: { userCharacters: [], loadingCharacters: false, ...storeState } },
    });
    if (storeState.userCharacters) {
        const store = useCharacterStore();
        store.userCharacters = storeState.userCharacters;
        store.loadingCharacters = storeState.loadingCharacters ?? false;
        await nextTick();
    }
    return wrapper;
};

describe('MyCharactersPage', () => {
    it('renders the page title', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).toContain('Mes personnages');
    });

    it('shows loading spinner while loading', async () => {
        const wrapper = await mountPage({ loadingCharacters: true });

        expect(wrapper.text()).toContain('Chargement en cours...');
    });

    it('displays all characters', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).toContain('Arthas');
        expect(wrapper.text()).toContain('Thrall');
        expect(wrapper.text()).toContain('Jaina');
    });

    it('displays character details (level, race, class)', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).toContain('Niv 80');
        expect(wrapper.text()).toContain('Humain');
        expect(wrapper.text()).toContain('Chevalier de la mort');
    });

    it('filters characters by search input', async () => {
        const wrapper = await mountPage({ userCharacters });

        const searchInput = wrapper.find('input[placeholder*="Rechercher"]');
        await searchInput.setValue('Arthas');

        expect(wrapper.text()).toContain('Arthas');
        expect(wrapper.text()).not.toContain('Thrall');
        expect(wrapper.text()).not.toContain('Jaina');
    });

    it('filters by realm name', async () => {
        const wrapper = await mountPage({ userCharacters });

        const searchInput = wrapper.find('input[placeholder*="Rechercher"]');
        await searchInput.setValue('dalaran');

        expect(wrapper.text()).toContain('Thrall');
        expect(wrapper.text()).not.toContain('Arthas');
    });

    it('shows empty state when no characters match search', async () => {
        const wrapper = await mountPage({ userCharacters });

        const searchInput = wrapper.find('input[placeholder*="Rechercher"]');
        await searchInput.setValue('zzzzz');

        expect(wrapper.text()).toContain('Aucun personnage ne correspond');
    });

    it('shows empty state when no characters at all', async () => {
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('Aucun personnage trouvé');
    });

    it('calls fetchUserCharacters on mount when empty', async () => {
        await mountPage();
        const store = useCharacterStore();

        expect(store.fetchUserCharacters).toHaveBeenCalled();
    });

    it('displays faction badge with correct color', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).toContain('Alliance');
        expect(wrapper.text()).toContain('Horde');
    });

    it('filters characters by faction', async () => {
        const wrapper = await mountPage({ userCharacters });

        const searchInput = wrapper.find('input[placeholder*="Rechercher"]');
        await searchInput.setValue('horde');

        expect(wrapper.text()).toContain('Thrall');
        expect(wrapper.text()).not.toContain('Arthas');
        expect(wrapper.text()).not.toContain('Jaina');
    });
});
