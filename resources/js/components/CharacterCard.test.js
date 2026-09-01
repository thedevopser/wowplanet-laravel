import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import CharacterCard from './CharacterCard.vue';

const baseCharacter = {
    name: 'Arthas',
    level: 80,
    race: 'Humain',
    class: 'Chevalier de la mort',
    classId: 6,
    realm: 'Hyjal',
    avatarUrl: 'https://render.worldofwarcraft.com/avatar.jpg',
    classIconUrl: 'https://render.worldofwarcraft.com/class.jpg',
    faction: 'Alliance',
    mountsCount: 150,
    petsCount: 200,
    exaltedCount: 42,
};

function mountCard(character = baseCharacter) {
    return mount(CharacterCard, {
        props: { character },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn })] },
    });
}

describe('CharacterCard', () => {
    it('affiche le badge de score fourni par le serveur', () => {
        const wrapper = mountCard({ ...baseCharacter, score: { global: 61.2, rank: 'Rare', dimensions: [] } });

        expect(wrapper.findComponent({ name: 'ScoreBadge' }).props('score')).toBe(61.2);
    });

    it('masque le badge quand le profil ne porte pas de score', () => {
        expect(mountCard().findComponent({ name: 'ScoreBadge' }).exists()).toBe(false);
    });

    it('displays character name', () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain('Arthas');
    });

    it('displays level, race, class and realm', () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain('Niv 80');
        expect(wrapper.text()).toContain('Humain');
        expect(wrapper.text()).toContain('Chevalier de la mort');
        expect(wrapper.text()).toContain('Hyjal');
    });

    it('displays mounts and pets counts', () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain('150');
        expect(wrapper.text()).toContain('200');
    });

    it('applies class color from classColors map', () => {
        const wrapper = mountCard();
        const nameElement = wrapper.find('h2');

        expect(nameElement.attributes('style')).toContain('#C41E3A');
    });

    it('falls back to white for unknown classId', () => {
        const character = { ...baseCharacter, classId: 99 };
        const wrapper = mountCard(character);
        const nameElement = wrapper.find('h2');

        expect(nameElement.attributes('style')).toContain('#FFFFFF');
    });

    it('renders avatar image', () => {
        const wrapper = mountCard();
        const avatar = wrapper.find('img[alt=""]');
        const mainAvatar = wrapper.findAll('img').find(img => img.attributes('src') === baseCharacter.avatarUrl);

        expect(mainAvatar).toBeTruthy();
    });

    it('displays completed reputations count', () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain('42');
        expect(wrapper.text()).toContain('Rép. terminées');
    });

    it('displays faction name', () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain('Alliance');
    });

    it('applies blue color for Alliance faction', () => {
        const wrapper = mountCard();
        const factionSpan = wrapper.findAll('span').find(s => s.text() === 'Alliance');

        expect(factionSpan.attributes('style')).toContain('#3b82f6');
    });

    it('applies red color for Horde faction', () => {
        const character = { ...baseCharacter, faction: 'Horde' };
        const wrapper = mountCard(character);
        const factionSpan = wrapper.findAll('span').find(s => s.text() === 'Horde');

        expect(factionSpan.attributes('style')).toContain('#ef4444');
    });
});
