import { describe, it, expect } from 'vitest';
import ProfessionsTab from './ProfessionsTab.vue';
import { mountWithPlugins } from '../tests/helpers';

const makeCharacter = (professions = []) => ({ professions });

const standardProfession = {
    profession_id: 164,
    profession_name: 'Forge',
    type: 'primary',
    is_archaeology: false,
    expansions: {
        11: {
            has_tier: true,
            tier_exists: true,
            completed: 5,
            total: 20,
            skill_points: 80,
            max_skill_points: 100,
            categories: [
                {
                    name: 'Armes',
                    completed: 2,
                    total: 5,
                    items: [
                        { id: 1, name: 'Épée en acier', is_completed: true, wowhead_spell_id: 9001 },
                        { id: 2, name: 'Bouclier lourd', is_completed: false, wowhead_spell_id: null },
                        { id: 3, name: 'Dague rapide', is_completed: true, wowhead_spell_id: 9003 },
                    ],
                },
                {
                    name: 'Armures',
                    completed: 3,
                    total: 8,
                    items: [
                        { id: 4, name: 'Plastron', is_completed: true, wowhead_spell_id: 9004 },
                    ],
                },
            ],
        },
        0: {
            has_tier: false,
            tier_exists: true,
            completed: 0,
            total: 10,
            skill_points: 0,
            max_skill_points: 300,
            categories: [],
        },
    },
};

const archaeologyProfession = {
    profession_id: 794,
    profession_name: 'Archéologie',
    type: 'secondary',
    is_archaeology: true,
    global_skill_points: 525,
    global_max_skill_points: 950,
    expansions: {
        0: { skill_points: 300, max_skill_points: 300 },
        11: { skill_points: 0, max_skill_points: 0 },
    },
};

const secondProfession = {
    profession_id: 202,
    profession_name: 'Ingénierie',
    type: 'primary',
    is_archaeology: false,
    expansions: {
        11: {
            has_tier: true,
            tier_exists: true,
            completed: 1,
            total: 5,
            skill_points: 10,
            max_skill_points: 100,
            categories: [
                { name: 'Gadgets', completed: 1, total: 5, items: [{ id: 10, name: 'Bombe', is_completed: true, wowhead_spell_id: 5000 }] },
            ],
        },
    },
};

const mountComponent = (professions = [], stubs = {}) =>
    mountWithPlugins(ProfessionsTab, {
        initialState: { character: { character: makeCharacter(professions) } },
        stubs: { ExpansionSelector: true, ...stubs },
    });

describe('ProfessionsTab', () => {
    it('displays empty state when no professions', async () => {
        const wrapper = await mountComponent([]);

        expect(wrapper.text()).toContain("Ce personnage n'a aucun métier.");
    });

    it('renders profession selector buttons', async () => {
        const wrapper = await mountComponent([standardProfession, archaeologyProfession]);
        const buttons = wrapper.findAll('button');

        expect(buttons.some(b => b.text().includes('Forge'))).toBe(true);
        expect(buttons.some(b => b.text().includes('Archéologie'))).toBe(true);
    });

    it('marks secondary professions with (sec.) label', async () => {
        const wrapper = await mountComponent([standardProfession, archaeologyProfession]);

        expect(wrapper.text()).toContain('(sec.)');
    });

    it('auto-selects the first profession', async () => {
        const wrapper = await mountComponent([standardProfession]);

        expect(wrapper.text()).toContain('Forge');
        // First profession button should have the active class
        const firstButton = wrapper.findAll('button')[0];
        expect(firstButton.classes()).toContain('bg-emerald-600');
    });

    // ─── Standard profession mode ───────────────────────────

    it('shows progress percentage and recipe count', async () => {
        const wrapper = await mountComponent([standardProfession]);

        expect(wrapper.text()).toContain('25%'); // 5/20 = 25%
        expect(wrapper.text()).toContain('5 / 20');
    });

    it('shows skill points when available', async () => {
        const wrapper = await mountComponent([standardProfession]);

        expect(wrapper.text()).toContain('80 / 100');
    });

    it('sorts categories alphabetically', async () => {
        const wrapper = await mountComponent([standardProfession]);
        const categoryNames = wrapper.findAll('.font-bold.text-slate-300').map(el => el.text());

        expect(categoryNames[0]).toBe('Armes');
        expect(categoryNames[1]).toBe('Armures');
    });

    it('shows "not learned" message for expansion without tier', async () => {
        const wrapper = await mountComponent([standardProfession]);

        // Switch to Classic (expansion 0) where has_tier=false
        const expSelector = wrapper.findComponent({ name: 'ExpansionSelector' });
        // Simulate expansion change via the component's activeExpansion ref
        // We need to find the component's internal state — use vm
        wrapper.vm.activeExpansion = 0;
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain("Vous n'avez pas appris cette compétence pour cette extension.");
        expect(wrapper.text()).toContain('0%');
    });

    // ─── Archaeology mode ───────────────────────────────────

    it('shows global skill points in archaeology mode', async () => {
        const wrapper = await mountComponent([archaeologyProfession]);

        expect(wrapper.text()).toContain('525 / 950 points');
        expect(wrapper.text()).toContain('55%'); // Math.round(525/950*100) = 55
    });

    it('does not show ExpansionSelector for archaeology', async () => {
        const wrapper = await mountComponent([archaeologyProfession]);
        const expSelector = wrapper.findComponent({ name: 'ExpansionSelector' });

        expect(expSelector.exists()).toBe(false);
    });

    // ─── Filtering & search ─────────────────────────────────

    it('filters recipes by search text', async () => {
        const wrapper = await mountComponent([standardProfession]);

        wrapper.vm.search = 'dague';
        await wrapper.vm.$nextTick();

        // Only Armes category should remain (contains "Dague rapide"), Armures filtered out
        const categoryNames = wrapper.findAll('.font-bold.text-slate-300').map(el => el.text());
        expect(categoryNames).toHaveLength(1);
        expect(categoryNames[0]).toBe('Armes');
    });

    it('shows empty result message when search matches nothing', async () => {
        const wrapper = await mountComponent([standardProfession]);

        wrapper.vm.search = 'zzzzzzzzzzz';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Aucun résultat trouvé.');
    });

    it('hides completed recipes when hideCompleted is true', async () => {
        const wrapper = await mountComponent([standardProfession]);

        wrapper.vm.hideCompleted = true;
        await wrapper.vm.$nextTick();

        // Armes has 1 incomplete (Bouclier lourd), Armures has 0 incomplete → filtered out
        const categoryNames = wrapper.findAll('.font-bold.text-slate-300').map(el => el.text());
        expect(categoryNames).toHaveLength(1);
        expect(categoryNames[0]).toBe('Armes');
    });

    // ─── Switching profession ───────────────────────────────

    it('resets state when switching profession', async () => {
        const wrapper = await mountComponent([standardProfession, secondProfession]);

        // Set some state on first profession
        wrapper.vm.search = 'test';
        wrapper.vm.page = 2;
        await wrapper.vm.$nextTick();

        // Click second profession button
        const buttons = wrapper.findAll('button');
        const engineeringBtn = buttons.find(b => b.text().includes('Ingénierie'));
        await engineeringBtn.trigger('click');

        expect(wrapper.vm.search).toBe('');
        expect(wrapper.vm.page).toBe(1);
    });

    // ─── Wowhead links ──────────────────────────────────────

    it('generates wowhead links with spell ID when available', async () => {
        const wrapper = await mountComponent([standardProfession]);

        // Expand Armes category (first alphabetically) to reveal items
        wrapper.vm.expandedCategory = 'Armes';
        await wrapper.vm.$nextTick();

        const links = wrapper.findAll('a[href*="wowhead.com"]');
        const spellLink = links.find(l => l.attributes('href').includes('/spell=9001'));
        expect(spellLink).toBeTruthy();
    });

    it('generates wowhead search link when no spell ID', async () => {
        const wrapper = await mountComponent([standardProfession]);

        // Expand Armes category which contains "Bouclier lourd" with no spell_id
        wrapper.vm.expandedCategory = 'Armes';
        await wrapper.vm.$nextTick();

        const links = wrapper.findAll('a[href*="wowhead.com"]');
        const searchLink = links.find(l => l.attributes('href').includes('/search?q='));
        expect(searchLink).toBeTruthy();
    });
});
