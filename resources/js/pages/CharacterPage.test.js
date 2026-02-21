import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import CharacterPage from './CharacterPage.vue';
import { useCharacterStore } from '../stores/character';

const characterData = {
    name: 'Arthas',
    level: 80,
    race: 'Humain',
    class: 'Chevalier de la mort',
    classId: 6,
    realm: 'Hyjal',
    avatarUrl: 'https://render.worldofwarcraft.com/avatar.jpg',
    classIconUrl: '',
    mountsCount: 150,
    petsCount: 200,
    mounts: [],
    pets: [],
    collections: {},
};

describe('CharacterPage', () => {
    it('shows loading spinner while loading', async () => {
        const wrapper = await mountWithPlugins(CharacterPage, {
            initialRoute: '/character/hyjal/arthas',
            initialState: { character: { loading: true, character: null } },
        });

        expect(wrapper.text()).toContain('Synchronisation en cours...');
    });

    it('calls fetchCharacter on mount', async () => {
        const wrapper = await mountWithPlugins(CharacterPage, {
            initialRoute: '/character/hyjal/arthas',
            initialState: { character: { loading: false, character: null } },
        });
        const store = useCharacterStore();

        expect(store.fetchCharacter).toHaveBeenCalledWith('hyjal', 'arthas');
    });

    it('renders CharacterCard when character is loaded', async () => {
        const wrapper = await mountWithPlugins(CharacterPage, {
            initialRoute: '/character/hyjal/arthas',
            initialState: { character: { loading: false, character: characterData } },
        });

        expect(wrapper.text()).toContain('Arthas');
        expect(wrapper.text()).toContain('Niv 80');
    });

    it('renders content type tabs', async () => {
        const wrapper = await mountWithPlugins(CharacterPage, {
            initialRoute: '/character/hyjal/arthas',
            initialState: { character: { loading: false, character: characterData } },
        });

        expect(wrapper.text()).toContain('Quêtes');
        expect(wrapper.text()).toContain('Hauts-faits');
        expect(wrapper.text()).toContain('Réputations');
        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('Mascottes');
    });

    it('displays mounts count in tab', async () => {
        const wrapper = await mountWithPlugins(CharacterPage, {
            initialRoute: '/character/hyjal/arthas',
            initialState: { character: { loading: false, character: characterData } },
        });

        expect(wrapper.text()).toContain('150');
        expect(wrapper.text()).toContain('200');
    });

    it('switches tabs on click', async () => {
        const wrapper = await mountWithPlugins(CharacterPage, {
            initialRoute: '/character/hyjal/arthas',
            initialState: { character: { loading: false, character: characterData } },
        });

        const tabButtons = wrapper.findAll('button');
        const mountsTab = tabButtons.find(btn => btn.text().includes('Montures'));
        await mountsTab.trigger('click');

        expect(wrapper.findComponent({ name: 'MountsTab' }).exists()).toBe(true);
    });
});
