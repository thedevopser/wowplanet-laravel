import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/character/hyjal/arthas', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import { mountWithPlugins } from '../tests/helpers';
import CharacterPage from './CharacterPage.vue';

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

const meta = {
    title: 'Arthas - Chevalier de la mort 80 | Hyjal | WowPlanet',
    description: 'Profil du personnage Arthas',
    ogTitle: 'Arthas',
    ogDescription: 'Profil du personnage Arthas',
    ogImage: 'https://example.com/avatar.jpg',
    ogUrl: 'https://example.com/character/hyjal/arthas',
    ogType: 'profile',
    canonicalUrl: 'https://example.com/character/hyjal/arthas',
    jsonLd: null,
};

function mountCharacterPage(character) {
    return mountWithPlugins(CharacterPage, {
        initialRoute: '/character/hyjal/arthas',
        props: { character, realm: 'hyjal', name: 'arthas', meta },
    });
}

describe('CharacterPage', () => {
    it('renders CharacterCard from the character prop', async () => {
        const wrapper = await mountCharacterPage(characterData);

        expect(wrapper.text()).toContain('Arthas');
        expect(wrapper.text()).toContain('Niv 80');
    });

    it('renders content type tabs', async () => {
        const wrapper = await mountCharacterPage(characterData);

        expect(wrapper.text()).toContain('Quêtes');
        expect(wrapper.text()).toContain('Hauts-faits');
        expect(wrapper.text()).toContain('Réputations');
        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('Mascottes');
    });

    it('displays mounts count in tab', async () => {
        const wrapper = await mountCharacterPage(characterData);

        expect(wrapper.text()).toContain('150');
        expect(wrapper.text()).toContain('200');
    });

    it('switches tabs on click', async () => {
        const wrapper = await mountCharacterPage(characterData);

        const tabButtons = wrapper.findAll('button');
        const mountsTab = tabButtons.find(btn => btn.text().includes('Montures'));
        await mountsTab.trigger('click');

        expect(wrapper.findComponent({ name: 'MountsTab' }).exists()).toBe(true);
    });

    it('shows a not-found message when character prop is null', async () => {
        const wrapper = await mountCharacterPage(null);

        expect(wrapper.text()).toContain('Personnage introuvable');
    });
});
