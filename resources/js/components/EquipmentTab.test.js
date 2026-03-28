import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import EquipmentTab from './EquipmentTab.vue';

const makeCharacter = (equipment = [], ilvl = 630) => ({
    avatarUrl: 'https://render.worldofwarcraft.com/avatar.jpg',
    ilvl,
    equipment,
});

const fullEquipment = [
    { slot: 'HEAD', slot_name: 'Tête', item_id: 100, name: 'Casque épique', item_level: 639, quality: 'EPIC', icon_url: 'https://wow.zamimg.com/images/wow/icons/medium/123.jpg' },
    { slot: 'NECK', slot_name: 'Cou', item_id: 101, name: 'Collier rare', item_level: 626, quality: 'RARE', icon_url: 'https://wow.zamimg.com/images/wow/icons/medium/124.jpg' },
    { slot: 'MAIN_HAND', slot_name: 'Main droite', item_id: 103, name: 'Épée légendaire', item_level: 645, quality: 'LEGENDARY', icon_url: 'https://wow.zamimg.com/images/wow/icons/medium/125.jpg' },
];

describe('EquipmentTab', () => {
    it('renders equipment heading', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        expect(wrapper.text()).toContain('Équipement');
    });

    it('renders average ilvl', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        expect(wrapper.text()).toContain('630');
    });

    it('renders item names', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        expect(wrapper.text()).toContain('Casque épique');
        expect(wrapper.text()).toContain('Collier rare');
        expect(wrapper.text()).toContain('Épée légendaire');
    });

    it('renders item levels', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        expect(wrapper.text()).toContain('639');
        expect(wrapper.text()).toContain('626');
        expect(wrapper.text()).toContain('645');
    });

    it('renders wowhead links', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        const links = wrapper.findAll('a[href*="wowhead.com/fr/item="]');
        expect(links.length).toBeGreaterThanOrEqual(3);
        expect(links[0].attributes('href')).toContain('/item=');
        expect(links[0].attributes('target')).toBe('_blank');
    });

    it('applies quality color classes', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        expect(wrapper.html()).toContain('text-purple-400');
        expect(wrapper.html()).toContain('text-blue-400');
        expect(wrapper.html()).toContain('text-orange-400');
    });

    it('renders avatar image', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter(fullEquipment) } });
        const img = wrapper.find('img[alt="Avatar"]');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://render.worldofwarcraft.com/avatar.jpg');
    });

    it('renders with empty equipment', async () => {
        const wrapper = await mountWithPlugins(EquipmentTab, { props: { character: makeCharacter([]) } });
        expect(wrapper.text()).toContain('Équipement');
    });
});
