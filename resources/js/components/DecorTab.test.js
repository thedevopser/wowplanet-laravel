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

describe('DecorTab', () => {
    it('renders heading and decor count', () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor, 2) } });

        expect(wrapper.text()).toContain('Décorations');
        expect(wrapper.text()).toContain('2');
        expect(wrapper.text()).toContain('/ 3 total');
    });

    it('sorts decor alphabetically', () => {
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(sampleDecor) } });
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

        // Type in search input
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

    it('paginates at 24 items per page', () => {
        const manyDecor = Array.from({ length: 30 }, (_, i) => ({
            id: i + 1,
            name: `Décor ${String(i + 1).padStart(2, '0')}`,
            is_completed: false,
            item_id: i + 100,
            icon_url: null,
        }));
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(manyDecor) } });

        // Should show pagination controls
        expect(wrapper.text()).toContain('1 / 2');
        // Only 24 items visible on first page
        const items = wrapper.findAll('.font-bold.text-slate-200');
        expect(items.length).toBe(24);
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
        const manyDecor = Array.from({ length: 30 }, (_, i) => ({
            id: i + 1,
            name: `Décor ${String(i + 1).padStart(2, '0')}`,
            is_completed: false,
            item_id: i + 100,
            icon_url: null,
        }));
        const wrapper = mount(DecorTab, { props: { character: makeCharacter(manyDecor) } });

        // Go to page 2
        const nextBtn = wrapper.findAll('button').find(b => b.text().includes('→'));
        await nextBtn.trigger('click');
        expect(wrapper.text()).toContain('2 / 2');

        // Search should reset to page 1
        const input = wrapper.find('input[type="text"]');
        await input.setValue('01');

        // Page should be reset (pagination may disappear with few results)
        expect(wrapper.vm.page).toBe(1);
    });
});
