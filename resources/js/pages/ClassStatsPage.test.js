import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/class-stats', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import { mountWithPlugins } from '../tests/helpers';
import ClassStatsPage from './ClassStatsPage.vue';
import { useCharacterStore } from '../stores/character';

const userCharacters = [
    { name: 'Arthas', classId: 6, className: 'Chevalier de la mort', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
    { name: 'Bolvar', classId: 6, className: 'Chevalier de la mort', realmSlug: 'hyjal', realm: 'Hyjal', level: 70, raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
    { name: 'Thrall', classId: 7, className: 'Chaman', realmSlug: 'dalaran', realm: 'Dalaran', level: 80, raceName: 'Orc', faction: 'Horde', avatarUrl: '' },
    { name: 'Jaina', classId: 8, className: 'Mage', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, raceName: 'Humaine', faction: 'Alliance', avatarUrl: '' },
    { name: 'Khadgar', classId: 8, className: 'Mage', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
    { name: 'Medivh', classId: 8, className: 'Mage', realmSlug: 'hyjal', realm: 'Hyjal', level: 80, raceName: 'Humain', faction: 'Alliance', avatarUrl: '' },
];

describe('ClassStatsPage', () => {
    it('renders the page title', async () => {
        const wrapper = await mountWithPlugins(ClassStatsPage, {
            initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {} } },
        });

        expect(wrapper.text()).toContain('Mes classes');
    });

    it('displays total character count', async () => {
        const wrapper = await mountWithPlugins(ClassStatsPage, {
            initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {} } },
        });

        expect(wrapper.text()).toContain('6');
    });

    it('shows loading spinner while loading', async () => {
        const wrapper = await mountWithPlugins(ClassStatsPage, {
            initialState: { character: { userCharacters: [], loadingCharacters: true, classIcons: {} } },
        });

        expect(wrapper.text()).toContain('Chargement en cours...');
    });

    it('displays podium with top 3 classes', async () => {
        const wrapper = await mountWithPlugins(ClassStatsPage, {
            initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {} } },
        });

        expect(wrapper.text()).toContain('Mage');
        expect(wrapper.text()).toContain('Chevalier de la mort');
        expect(wrapper.text()).toContain('Chaman');
    });

    it('sorts classes by count descending (Mage first with 3)', async () => {
        const wrapper = await mountWithPlugins(ClassStatsPage, {
            initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {} } },
        });

        const countElements = wrapper.findAll('.font-black.font-mono.text-white');
        const counts = countElements.map(el => parseInt(el.text())).sort((a, b) => b - a);

        expect(counts[0]).toBe(3);
        expect(counts[1]).toBe(2);
        expect(counts[2]).toBe(1);
    });

    it('calls fetchClassIcons on mount', async () => {
        const wrapper = await mountWithPlugins(ClassStatsPage, {
            initialState: { character: { userCharacters, loadingCharacters: false, classIcons: {} } },
        });
        const store = useCharacterStore();

        expect(store.fetchClassIcons).toHaveBeenCalled();
    });

    it('shows empty state when no characters', async () => {
        const wrapper = await mountWithPlugins(ClassStatsPage, {
            initialState: { character: { userCharacters: [], loadingCharacters: false, classIcons: {} } },
        });

        expect(wrapper.text()).toContain('Aucun personnage trouvé');
    });
});
