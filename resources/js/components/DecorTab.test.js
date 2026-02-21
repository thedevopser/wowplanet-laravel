import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import DecorTab from './DecorTab.vue';

const makeCharacter = (decor = [], decorCount = null) => ({
    decorCount: decorCount ?? decor.filter(d => d.is_completed).length,
    decor,
});

const sampleDecor = [
    { id: 1, name: 'Chandelier doré', is_completed: true, item_id: 100, icon_url: 'https://example.com/a.png' },
    { id: 2, name: 'Armoire sombre', is_completed: false, item_id: 200, icon_url: null },
    { id: 3, name: 'Bureau ancien', is_completed: true, item_id: null, icon_url: 'https://example.com/b.png' },
];

/** Sample decor with category/source for categorized view tests */
const categorizedDecor = [
    { id: 1, name: 'Chandelier doré', is_completed: true, item_id: 100, icon_url: 'https://example.com/a.png', category: 'The War Within', source: 'Quest' },
    { id: 2, name: 'Armoire sombre', is_completed: false, item_id: 200, icon_url: null, category: 'The War Within', source: 'Quest' },
    { id: 3, name: 'Bureau ancien', is_completed: true, item_id: null, icon_url: 'https://example.com/b.png', category: 'The War Within', source: 'Achievement' },
];

describe('DecorTab', () => {
    it('renders heading and decor count', () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(categorizedDecor, 2) } });

        expect(wrapper.text()).toContain('Décorations');
        expect(wrapper.text()).toContain('2');
        expect(wrapper.text()).toContain('2 / 3');
    });

    it('sorts decor alphabetically', () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor) } });
        // Uncategorized items are rendered as flat list sorted alphabetically
        const names = wrapper.findAll('.font-bold.text-slate-200').map(el => el.text());

        expect(names[0]).toBe('Armoire sombre');
        expect(names[1]).toBe('Bureau ancien');
        expect(names[2]).toBe('Chandelier doré');
    });

    it('shows "Obtenue" badge for completed decor', () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor) } });
        const badges = wrapper.findAll('.text-green-400');

        expect(badges.length).toBe(2);
        expect(badges[0].text()).toBe('Obtenue');
    });

    it('filters decor by search text', async () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor) } });

        const input = wrapper.find('input[type="text"]');
        await input.setValue('armoire');

        const names = wrapper.findAll('.font-bold.text-slate-200').map(el => el.text());
        expect(names).toHaveLength(1);
        expect(names[0]).toBe('Armoire sombre');
    });

    it('hides completed decor when toggle is clicked', async () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor) } });

        const toggleBtn = wrapper.findAll('button').find(b => b.text().includes('Masquer'));
        await toggleBtn.trigger('click');

        const names = wrapper.findAll('.font-bold.text-slate-200').map(el => el.text());
        expect(names).toHaveLength(1);
        expect(names[0]).toBe('Armoire sombre');
    });

    it('shows empty result message when no matches', async () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor) } });

        const input = wrapper.find('input[type="text"]');
        await input.setValue('zzzzzzzzz');

        expect(wrapper.text()).toContain('Aucun résultat trouvé.');
    });

    it('paginates source cards at 8 per page', () => {
        // Create items spread across 10 different sources to trigger pagination (8 per page)
        const sourceNames = [
            'Quest', 'Achievement', 'Vendor', 'Raid Drop', 'Dungeon Drop',
            'Reputation', 'Treasure', 'Delves', 'Rare', 'Drop',
        ];
        const manyDecor = sourceNames.map((source, i) => ({
            id: i + 1,
            name: `Décor ${String(i + 1).padStart(2, '0')}`,
            is_completed: false,
            item_id: i + 100,
            icon_url: null,
            category: 'The War Within',
            source,
        }));
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(manyDecor) } });

        // Should show pagination controls (10 sources / 8 per page = 2 pages)
        expect(wrapper.text()).toContain('1 / 2');
        // Only 8 source cards visible on first page
        const sourceCards = wrapper.findAll('.bg-slate-800\\/40.border');
        expect(sourceCards.length).toBe(8);
    });

    it('generates wowhead link with item_id when available', () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor) } });
        const links = wrapper.findAll('a[href*="wowhead.com"]');

        const itemLink = links.find(l => l.attributes('href').includes('/item=100'));
        expect(itemLink).toBeTruthy();
    });

    it('generates wowhead search link when no item_id', () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor) } });
        const links = wrapper.findAll('a[href*="wowhead.com"]');

        const searchLink = links.find(l => l.attributes('href').includes('/search?q='));
        expect(searchLink).toBeTruthy();
    });

    it('displays item IDs', () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor) } });

        expect(wrapper.text()).toContain('ID: 1');
        expect(wrapper.text()).toContain('ID: 2');
        expect(wrapper.text()).toContain('ID: 3');
    });

    it('resets page when search changes', async () => {
        // Create items spread across 10 different sources to trigger pagination
        const sourceNames = [
            'Quest', 'Achievement', 'Vendor', 'Raid Drop', 'Dungeon Drop',
            'Reputation', 'Treasure', 'Delves', 'Rare', 'Drop',
        ];
        const manyDecor = sourceNames.map((source, i) => ({
            id: i + 1,
            name: `Décor ${String(i + 1).padStart(2, '0')}`,
            is_completed: false,
            item_id: i + 100,
            icon_url: null,
            category: 'The War Within',
            source,
        }));
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(manyDecor) } });

        // Go to page 2
        const nextBtn = wrapper.findAll('button').find(b => b.text().includes('→'));
        await nextBtn.trigger('click');
        expect(wrapper.text()).toContain('2 / 2');

        // Search should reset to page 1
        const input = wrapper.find('input[type="text"]');
        await input.setValue('01');

        expect(wrapper.vm.page).toBe(1);
    });
});
