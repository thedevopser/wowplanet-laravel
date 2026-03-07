import { describe, it, expect } from 'vitest';
import { createAccountAggregator, DIMENSION_LABELS, DIMENSION_COLORS, WEIGHTS } from './accountScoreAggregator';

const char1 = {
    mounts: [{ id: 1, is_completed: true }, { id: 2, is_completed: false }],
    pets: [{ id: 1, is_completed: true }],
    decor: [{ id: 1, is_completed: false }],
    collections: {
        1: {
            quests: { total: 3, completed: 1, zones: [{ name: 'Zone1', items: [{ id: 101, is_completed: true }, { id: 102, is_completed: false }, { id: 103, is_completed: false }] }] },
            achievements: { total: 2, completed: 1, categories: [{ name: 'Cat1', items: [{ id: 201, is_completed: true }, { id: 202, is_completed: false }] }] },
            reputations: { completed: 3, total: 5 },
        },
    },
    professions: [{ profession_id: 1, expansions: { 1: { completed: 2, total: 10, skill_points: 50, max_skill_points: 100 } } }],
};

const char2 = {
    mounts: [{ id: 1, is_completed: true }, { id: 2, is_completed: true }],
    pets: [{ id: 1, is_completed: false }],
    decor: [],
    collections: {
        1: {
            quests: { total: 3, completed: 2, zones: [{ name: 'Zone1', items: [{ id: 101, is_completed: false }, { id: 102, is_completed: true }, { id: 103, is_completed: false }] }] },
            achievements: { total: 2, completed: 1, categories: [{ name: 'Cat1', items: [{ id: 201, is_completed: false }, { id: 202, is_completed: true }] }] },
            reputations: { completed: 4, total: 5 },
        },
    },
    professions: [{ profession_id: 2, expansions: { 1: { completed: 5, total: 8, skill_points: 80, max_skill_points: 100 } } }],
};

describe('createAccountAggregator', () => {
    it('returns object with expected methods', () => {
        const agg = createAccountAggregator();
        expect(typeof agg.mergeCharacter).toBe('function');
        expect(typeof agg.getScore).toBe('function');
        expect(typeof agg.getLoadedCount).toBe('function');
        expect(typeof agg.getVirtualProfile).toBe('function');
    });

    it('getLoadedCount starts at 0', () => {
        const agg = createAccountAggregator();
        expect(agg.getLoadedCount()).toBe(0);
    });

    it('mergeCharacter increments loadedCount', () => {
        const agg = createAccountAggregator();
        agg.mergeCharacter(char1);
        expect(agg.getLoadedCount()).toBe(1);
        agg.mergeCharacter(char2);
        expect(agg.getLoadedCount()).toBe(2);
    });

    it('getVirtualProfile returns null before any merge', () => {
        const agg = createAccountAggregator();
        expect(agg.getVirtualProfile()).toBeNull();
    });

    it('mergeCharacter with null does not increment loadedCount or create profile', () => {
        const agg = createAccountAggregator();
        agg.mergeCharacter(null);
        expect(agg.getLoadedCount()).toBe(0);
        expect(agg.getVirtualProfile()).toBeNull();
    });

    it('mergeCharacter with single character produces valid virtual profile', () => {
        const agg = createAccountAggregator();
        agg.mergeCharacter(char1);

        const profile = agg.getVirtualProfile();
        expect(profile).not.toBeNull();
        expect(profile.mounts).toHaveLength(2);
        expect(profile.pets).toHaveLength(1);
        expect(profile.decor).toHaveLength(1);
        expect(profile.professions).toHaveLength(1);
        expect(profile.collections[1]).toBeDefined();
        expect(profile.collections[1].quests.completed).toBe(1);
        expect(profile.collections[1].achievements.completed).toBe(1);
    });

    it('unions quest completions across characters', () => {
        const agg = createAccountAggregator();
        agg.mergeCharacter(char1);
        agg.mergeCharacter(char2);

        const profile = agg.getVirtualProfile();
        // char1 completed quest 101, char2 completed quest 102 => union = 2
        expect(profile.collections[1].quests.completed).toBe(2);

        const questItems = profile.collections[1].quests.zones[0].items;
        expect(questItems.find(i => i.id === 101).is_completed).toBe(true);
        expect(questItems.find(i => i.id === 102).is_completed).toBe(true);
        expect(questItems.find(i => i.id === 103).is_completed).toBe(false);
    });

    it('unions achievement completions across characters', () => {
        const agg = createAccountAggregator();
        agg.mergeCharacter(char1);
        agg.mergeCharacter(char2);

        const profile = agg.getVirtualProfile();
        // char1 completed ach 201, char2 completed ach 202 => union = 2
        expect(profile.collections[1].achievements.completed).toBe(2);

        const achItems = profile.collections[1].achievements.categories[0].items;
        expect(achItems.find(i => i.id === 201).is_completed).toBe(true);
        expect(achItems.find(i => i.id === 202).is_completed).toBe(true);
    });

    it('account-wide collections (mounts/pets/decor) taken from first character', () => {
        const agg = createAccountAggregator();
        agg.mergeCharacter(char1);
        agg.mergeCharacter(char2);

        const profile = agg.getVirtualProfile();
        // Mounts/pets/decor should be from char1 (first merged)
        expect(profile.mounts).toEqual(char1.mounts);
        expect(profile.pets).toEqual(char1.pets);
        expect(profile.decor).toEqual(char1.decor);
    });

    it('reputations keep best per expansion', () => {
        const agg = createAccountAggregator();
        agg.mergeCharacter(char1);
        agg.mergeCharacter(char2);

        const profile = agg.getVirtualProfile();
        // char1 reputations: 3/5, char2 reputations: 4/5 => best = 4/5
        expect(profile.collections[1].reputations.completed).toBe(4);
        expect(profile.collections[1].reputations.total).toBe(5);
    });

    it('professions merge across characters with different professions', () => {
        const agg = createAccountAggregator();
        agg.mergeCharacter(char1);
        agg.mergeCharacter(char2);

        const profile = agg.getVirtualProfile();
        // char1 has profession_id 1, char2 has profession_id 2
        expect(profile.professions).toHaveLength(2);

        const prof1 = profile.professions.find(p => p.profession_id === 1);
        const prof2 = profile.professions.find(p => p.profession_id === 2);
        expect(prof1).toBeDefined();
        expect(prof2).toBeDefined();
        expect(prof1.expansions[1].completed).toBe(2);
        expect(prof2.expansions[1].completed).toBe(5);
    });

    it('professions merge same profession keeps best values', () => {
        const charA = {
            mounts: [], pets: [], decor: [],
            collections: {
                1: { quests: { total: 0, completed: 0, zones: [] }, achievements: { total: 0, completed: 0, categories: [] }, reputations: { completed: 0, total: 0 } },
            },
            professions: [{ profession_id: 1, expansions: { 1: { completed: 3, total: 10, skill_points: 50, max_skill_points: 100 } } }],
        };
        const charB = {
            mounts: [], pets: [], decor: [],
            collections: {
                1: { quests: { total: 0, completed: 0, zones: [] }, achievements: { total: 0, completed: 0, categories: [] }, reputations: { completed: 0, total: 0 } },
            },
            professions: [{ profession_id: 1, expansions: { 1: { completed: 7, total: 10, skill_points: 90, max_skill_points: 100 } } }],
        };

        const agg = createAccountAggregator();
        agg.mergeCharacter(charA);
        agg.mergeCharacter(charB);

        const profile = agg.getVirtualProfile();
        const prof = profile.professions.find(p => p.profession_id === 1);
        expect(prof.expansions[1].completed).toBe(7);
        expect(prof.expansions[1].skill_points).toBe(90);
    });

    it('getScore returns computed score after merge', () => {
        const agg = createAccountAggregator();
        agg.mergeCharacter(char1);

        const score = agg.getScore();
        expect(score).not.toBeNull();
        expect(typeof score.global).toBe('number');
        expect(score.dimensions).toBeDefined();
        expect(Object.keys(score.dimensions)).toHaveLength(7);
    });

    it('getScore returns null before any merge', () => {
        const agg = createAccountAggregator();
        expect(agg.getScore()).toBeNull();
    });
});

describe('re-exports', () => {
    it('re-exports DIMENSION_LABELS', () => {
        expect(DIMENSION_LABELS).toBeDefined();
        expect(Object.keys(DIMENSION_LABELS)).toHaveLength(7);
    });

    it('re-exports DIMENSION_COLORS', () => {
        expect(DIMENSION_COLORS).toBeDefined();
        expect(Object.keys(DIMENSION_COLORS)).toHaveLength(7);
    });

    it('re-exports WEIGHTS', () => {
        expect(WEIGHTS).toBeDefined();
        expect(Object.keys(WEIGHTS)).toHaveLength(7);
    });
});
