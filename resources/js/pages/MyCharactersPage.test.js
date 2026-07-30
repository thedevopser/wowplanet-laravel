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
import { useFavoriteStore } from '../stores/favorites';

const userCharacters = [
    { name: 'Arthas', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, classId: 6, className: 'Chevalier de la mort', raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
    { name: 'Thrall', realmSlug: 'dalaran', realm: 'Dalaran', level: 70, classId: 7, className: 'Chaman', raceName: 'Orc', faction: 'Horde', avatarUrl: '' },
    { name: 'Jaina', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, classId: 8, className: 'Mage', raceName: 'Humaine', faction: 'Alliance', avatarUrl: '' },
];

const mountPage = async (storeState = {}, favorites = []) => {
    const wrapper = await mountWithPlugins(MyCharactersPage, {
        initialState: {
            character: { userCharacters: [], loadingCharacters: false, ...storeState },
            favorites: { favorites, loading: false },
        },
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

    // ─── Favorites ────────────────────────────────────────

    const favorite = (realm, name, sortOrder = 0) => ({
        id: sortOrder + 1,
        realm_slug: realm,
        character_name: name,
        sort_order: sortOrder,
    });

    it('hides both section headings when there is no favorite', async () => {
        const wrapper = await mountPage({ userCharacters });

        expect(wrapper.text()).not.toContain('Favoris');
        expect(wrapper.text()).not.toContain('Tous mes personnages');
    });

    it('shows a favorites section with the counter', async () => {
        const wrapper = await mountPage({ userCharacters }, [favorite('hyjal', 'jaina')]);

        expect(wrapper.text()).toContain('Favoris');
        expect(wrapper.text()).toContain('1/3');
        expect(wrapper.text()).toContain('Tous mes personnages');
    });

    it('removes favorites from the main list', async () => {
        const wrapper = await mountPage({ userCharacters }, [favorite('hyjal', 'jaina')]);

        const sections = wrapper.findAll('section');
        expect(sections).toHaveLength(2);
        expect(sections[0].text()).toContain('Jaina');
        expect(sections[1].text()).not.toContain('Jaina');
        expect(sections[1].text()).toContain('Arthas');
        expect(sections[1].text()).toContain('Thrall');
    });

    it('keeps favorites in the order they were starred', async () => {
        const wrapper = await mountPage(
            { userCharacters },
            [favorite('hyjal', 'jaina', 0), favorite('dalaran', 'thrall', 1)]
        );

        const names = wrapper.findAll('section')[0].findAll('.font-bold.truncate').map(el => el.text());
        expect(names).toEqual(['Jaina', 'Thrall']);
    });

    it('applies the search to both sections', async () => {
        const wrapper = await mountPage({ userCharacters }, [favorite('hyjal', 'jaina')]);

        await wrapper.find('input[placeholder*="Rechercher"]').setValue('arthas');

        expect(wrapper.text()).toContain('Arthas');
        expect(wrapper.text()).not.toContain('Jaina');
    });

    it('shows the no-results message when the search empties both sections', async () => {
        const wrapper = await mountPage({ userCharacters }, [favorite('hyjal', 'jaina')]);

        await wrapper.find('input[placeholder*="Rechercher"]').setValue('zzzzz');

        expect(wrapper.text()).toContain('Aucun personnage ne correspond');
    });

    it('disables the remaining stars once three characters are starred', async () => {
        const wrapper = await mountPage(
            { userCharacters },
            [favorite('hyjal', 'arthas', 0), favorite('dalaran', 'thrall', 1), favorite('hyjal', 'jaina', 2)]
        );

        expect(wrapper.findAll('section')).toHaveLength(1);
        expect(wrapper.text()).toContain('3/3');
    });

    it('toggles a favorite when the star is clicked', async () => {
        const wrapper = await mountPage({ userCharacters });
        const favoritesStore = useFavoriteStore();

        await wrapper.find('button[aria-label="Ajouter aux favoris"]').trigger('click');

        expect(favoritesStore.toggleFavorite).toHaveBeenCalledWith('hyjal', 'Arthas');
    });

    it('fetches favorites on mount when authenticated', async () => {
        await mountPage({ userCharacters, isAuthenticated: true });
        const favoritesStore = useFavoriteStore();

        expect(favoritesStore.fetchFavorites).toHaveBeenCalled();
    });
});
