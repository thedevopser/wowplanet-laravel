import { describe, it, expect } from 'vitest';
import {
    computeScore,
    getScoreColor,
    getRankColorHex,
    getScoreTailwindColor,
    WEIGHTS,
    DIMENSION_LABELS,
    DIMENSION_COLORS,
} from './scoreCalculator';

const DIMENSION_KEYS = ['quests', 'achievements', 'reputations', 'mounts', 'pets', 'decor', 'professions'];

describe('WEIGHTS', () => {
    it('sums to 1.0', () => {
        const sum = Object.values(WEIGHTS).reduce((a, b) => a + b, 0);
        expect(sum).toBeCloseTo(1.0);
    });

    it('has all 7 dimension keys', () => {
        expect(Object.keys(WEIGHTS)).toEqual(expect.arrayContaining(DIMENSION_KEYS));
        expect(Object.keys(WEIGHTS)).toHaveLength(7);
    });
});

describe('DIMENSION_LABELS', () => {
    it('has all 7 dimension keys', () => {
        expect(Object.keys(DIMENSION_LABELS)).toEqual(expect.arrayContaining(DIMENSION_KEYS));
        expect(Object.keys(DIMENSION_LABELS)).toHaveLength(7);
    });

    it('values are non-empty strings', () => {
        for (const label of Object.values(DIMENSION_LABELS)) {
            expect(typeof label).toBe('string');
            expect(label.length).toBeGreaterThan(0);
        }
    });
});

describe('DIMENSION_COLORS', () => {
    it('has all 7 dimension keys', () => {
        expect(Object.keys(DIMENSION_COLORS)).toEqual(expect.arrayContaining(DIMENSION_KEYS));
        expect(Object.keys(DIMENSION_COLORS)).toHaveLength(7);
    });

    it('values are hex color strings', () => {
        for (const color of Object.values(DIMENSION_COLORS)) {
            expect(color).toMatch(/^#[0-9a-fA-F]{6}$/);
        }
    });
});

describe('computeScore', () => {
    it('returns null for null input', () => {
        expect(computeScore(null)).toBeNull();
    });

    it('returns null for undefined input', () => {
        expect(computeScore(undefined)).toBeNull();
    });

    it('returns 0 global score for empty character', () => {
        const result = computeScore({});
        expect(result.global).toBe(0);
    });

    it('dimensions have completed, total, and score properties', () => {
        const result = computeScore({});
        for (const key of DIMENSION_KEYS) {
            expect(result.dimensions[key]).toHaveProperty('completed');
            expect(result.dimensions[key]).toHaveProperty('total');
            expect(result.dimensions[key]).toHaveProperty('score');
        }
    });

    it('computes correct dimensions and weighted global for a full character', () => {
        const character = {
            collections: {
                1: {
                    quests: { completed: 50, total: 100 },
                    achievements: { completed: 30, total: 100 },
                    reputations: { completed: 10, total: 20 },
                },
            },
            mounts: [{ is_completed: true }, { is_completed: false }, { is_completed: true }],
            pets: [{ is_completed: true }, { is_completed: false }],
            decor: [{ is_completed: true }],
            professions: [{ expansions: { 1: { completed: 5, total: 10, skill_points: 100, max_skill_points: 200 } } }],
        };

        const result = computeScore(character);

        // quests: 50/100 = 50%
        expect(result.dimensions.quests.completed).toBe(50);
        expect(result.dimensions.quests.total).toBe(100);
        expect(result.dimensions.quests.score).toBe(50);

        // achievements: 30/100 = 30%
        expect(result.dimensions.achievements.completed).toBe(30);
        expect(result.dimensions.achievements.total).toBe(100);
        expect(result.dimensions.achievements.score).toBe(30);

        // reputations: 10/20 = 50%
        expect(result.dimensions.reputations.completed).toBe(10);
        expect(result.dimensions.reputations.total).toBe(20);
        expect(result.dimensions.reputations.score).toBe(50);

        // mounts: 2/3 = 66.67%
        expect(result.dimensions.mounts.completed).toBe(2);
        expect(result.dimensions.mounts.total).toBe(3);
        expect(result.dimensions.mounts.score).toBeCloseTo(66.667, 1);

        // pets: 1/2 = 50%
        expect(result.dimensions.pets.completed).toBe(1);
        expect(result.dimensions.pets.total).toBe(2);
        expect(result.dimensions.pets.score).toBe(50);

        // decor: 1/1 = 100%
        expect(result.dimensions.decor.completed).toBe(1);
        expect(result.dimensions.decor.total).toBe(1);
        expect(result.dimensions.decor.score).toBe(100);

        // professions: 5/10 = 50% (recipes available, so uses recipes)
        expect(result.dimensions.professions.completed).toBe(5);
        expect(result.dimensions.professions.total).toBe(10);
        expect(result.dimensions.professions.score).toBe(50);

        // global = 50*0.15 + 30*0.25 + 50*0.15 + 66.667*0.15 + 50*0.10 + 100*0.10 + 50*0.10
        // = 7.5 + 7.5 + 7.5 + 10.0 + 5.0 + 10.0 + 5.0 = 52.5
        const expectedGlobal = 50 * 0.15 + 30 * 0.25 + 50 * 0.15 + (200 / 3) * 0.15 + 50 * 0.10 + 100 * 0.10 + 50 * 0.10;
        expect(result.global).toBe(Math.round(expectedGlobal * 10) / 10);
    });

    it('sums across multiple expansions in collections', () => {
        const character = {
            collections: {
                1: { quests: { completed: 10, total: 20 } },
                2: { quests: { completed: 5, total: 30 } },
            },
        };

        const result = computeScore(character);
        expect(result.dimensions.quests.completed).toBe(15);
        expect(result.dimensions.quests.total).toBe(50);
        expect(result.dimensions.quests.score).toBe(30);
    });

    it('falls back to skill_points when recipeTotal is 0', () => {
        const character = {
            professions: [{ expansions: { 1: { completed: 0, total: 0, skill_points: 75, max_skill_points: 100 } } }],
        };

        const result = computeScore(character);
        expect(result.dimensions.professions.completed).toBe(75);
        expect(result.dimensions.professions.total).toBe(100);
        expect(result.dimensions.professions.score).toBe(75);
    });
});

describe('getScoreColor', () => {
    it.each([
        [100, '#22c55e'],
        [75, '#22c55e'],
        [74, '#eab308'],
        [50, '#eab308'],
        [49, '#f97316'],
        [25, '#f97316'],
        [24, '#ef4444'],
        [0, '#ef4444'],
    ])('score %i returns %s', (score, expected) => {
        expect(getScoreColor(score)).toBe(expected);
    });
});

describe('getRankColorHex', () => {
    it.each([
        [100, '#f97316'],
        [90, '#f97316'],
        [89, '#a855f7'],
        [75, '#a855f7'],
        [74, '#3b82f6'],
        [50, '#3b82f6'],
        [49, '#22c55e'],
        [25, '#22c55e'],
        [24, '#94a3b8'],
        [0, '#94a3b8'],
    ])('score %i returns %s', (score, expected) => {
        expect(getRankColorHex(score)).toBe(expected);
    });
});

describe('getScoreTailwindColor', () => {
    it.each([
        [100, 'text-green-400'],
        [75, 'text-green-400'],
        [74, 'text-yellow-400'],
        [50, 'text-yellow-400'],
        [49, 'text-orange-400'],
        [25, 'text-orange-400'],
        [24, 'text-red-400'],
        [0, 'text-red-400'],
    ])('score %i returns %s', (score, expected) => {
        expect(getScoreTailwindColor(score)).toBe(expected);
    });
});
