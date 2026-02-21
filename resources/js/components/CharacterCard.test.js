import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
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

describe('CharacterCard', () => {
    it('displays character name', () => {
        const wrapper = mount(CharacterCard, { props: { character: baseCharacter } });

        expect(wrapper.text()).toContain('Arthas');
    });

    it('displays level, race, class and realm', () => {
        const wrapper = mount(CharacterCard, { props: { character: baseCharacter } });

        expect(wrapper.text()).toContain('Niv 80');
        expect(wrapper.text()).toContain('Humain');
        expect(wrapper.text()).toContain('Chevalier de la mort');
        expect(wrapper.text()).toContain('Hyjal');
    });

    it('displays mounts and pets counts', () => {
        const wrapper = mount(CharacterCard, { props: { character: baseCharacter } });

        expect(wrapper.text()).toContain('150');
        expect(wrapper.text()).toContain('200');
    });

    it('applies class color from classColors map', () => {
        const wrapper = mount(CharacterCard, { props: { character: baseCharacter } });
        const nameElement = wrapper.find('h2');

        expect(nameElement.attributes('style')).toContain('#C41E3A');
    });

    it('falls back to white for unknown classId', () => {
        const character = { ...baseCharacter, classId: 99 };
        const wrapper = mount(CharacterCard, { props: { character } });
        const nameElement = wrapper.find('h2');

        expect(nameElement.attributes('style')).toContain('#FFFFFF');
    });

    it('renders avatar image', () => {
        const wrapper = mount(CharacterCard, { props: { character: baseCharacter } });
        const avatar = wrapper.find('img[alt=""]');
        const mainAvatar = wrapper.findAll('img').find(img => img.attributes('src') === baseCharacter.avatarUrl);

        expect(mainAvatar).toBeTruthy();
    });

    it('displays completed reputations count', () => {
        const wrapper = mount(CharacterCard, { props: { character: baseCharacter } });

        expect(wrapper.text()).toContain('42');
        expect(wrapper.text()).toContain('Rép. terminées');
    });

    it('displays faction name', () => {
        const wrapper = mount(CharacterCard, { props: { character: baseCharacter } });

        expect(wrapper.text()).toContain('Alliance');
    });

    it('applies blue color for Alliance faction', () => {
        const wrapper = mount(CharacterCard, { props: { character: baseCharacter } });
        const factionSpan = wrapper.findAll('span').find(s => s.text() === 'Alliance');

        expect(factionSpan.attributes('style')).toContain('#3b82f6');
    });

    it('applies red color for Horde faction', () => {
        const character = { ...baseCharacter, faction: 'Horde' };
        const wrapper = mount(CharacterCard, { props: { character } });
        const factionSpan = wrapper.findAll('span').find(s => s.text() === 'Horde');

        expect(factionSpan.attributes('style')).toContain('#ef4444');
    });
});
