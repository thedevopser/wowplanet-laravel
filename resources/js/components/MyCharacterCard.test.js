import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import { mountWithPlugins } from '../tests/helpers';
import MyCharacterCard from './MyCharacterCard.vue';

const character = {
    name: 'Thrall',
    realmSlug: 'hyjal',
    realm: 'Hyjal',
    level: 80,
    classId: 7,
    className: 'Chaman',
    raceName: 'Orc',
    faction: 'Horde',
    avatarUrl: '',
};

const mountCard = (props = {}) => mountWithPlugins(MyCharacterCard, {
    props: { character, ...props },
});

describe('MyCharacterCard', () => {
    it('renders the character summary', async () => {
        const wrapper = await mountCard();

        expect(wrapper.text()).toContain('Thrall');
        expect(wrapper.text()).toContain('Hyjal');
        expect(wrapper.text()).toContain('Niv 80');
        expect(wrapper.text()).toContain('Chaman');
        expect(wrapper.find('a').attributes('href')).toBe('/character/hyjal/thrall');
    });

    it('shows the initial when there is no avatar', async () => {
        const wrapper = await mountCard();

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('T');
    });

    it('shows the avatar when available', async () => {
        const wrapper = await mountCard({ character: { ...character, avatarUrl: 'https://example.test/a.jpg' } });

        expect(wrapper.find('img').attributes('src')).toBe('https://example.test/a.jpg');
    });

    it('labels the star for adding when not a favorite', async () => {
        const wrapper = await mountCard();
        const button = wrapper.find('button');

        expect(button.attributes('aria-label')).toBe('Ajouter aux favoris');
        expect(button.attributes('aria-pressed')).toBe('false');
    });

    it('labels the star for removing when already a favorite', async () => {
        const wrapper = await mountCard({ isFavorite: true });
        const button = wrapper.find('button');

        expect(button.attributes('aria-label')).toBe('Retirer des favoris');
        expect(button.attributes('aria-pressed')).toBe('true');
    });

    it('emits toggle-favorite on click', async () => {
        const wrapper = await mountCard();

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('toggle-favorite')).toHaveLength(1);
    });

    it('disables the star with a tooltip when the limit is reached', async () => {
        const wrapper = await mountCard({ favoriteDisabled: true });
        const button = wrapper.find('button');

        expect(button.attributes('disabled')).toBeDefined();
        expect(button.attributes('title')).toBe('3 favoris maximum');

        await button.trigger('click');
        expect(wrapper.emitted('toggle-favorite')).toBeUndefined();
    });
});
