import { describe, it, expect } from 'vitest';
import {
    DIMENSION_COLORS,
    dimensionColor,
    rankClass,
    applicableDimensions,
    getScoreColor,
    getRankColorHex,
    getScoreTailwindColor,
} from './scoreDisplay';

describe('DIMENSION_COLORS', () => {
    it('couvre les neuf dimensions du score', () => {
        const keys = ['quests', 'achievements', 'reputations', 'raids', 'mounts', 'transmog', 'pets', 'decor', 'professions'];
        expect(Object.keys(DIMENSION_COLORS).sort()).toEqual(keys.sort());
    });

    it('ne contient que des couleurs hexadécimales', () => {
        for (const color of Object.values(DIMENSION_COLORS)) {
            expect(color).toMatch(/^#[0-9a-f]{6}$/i);
        }
    });
});

describe('dimensionColor', () => {
    it('renvoie la couleur de la dimension', () => {
        expect(dimensionColor('transmog')).toBe(DIMENSION_COLORS.transmog);
    });

    it('retombe sur une teinte neutre pour une clé inconnue', () => {
        expect(dimensionColor('pvp')).toBe('#64748b');
    });
});

describe('rankClass', () => {
    it.each([
        ['Légendaire', 'orange'],
        ['Épique', 'purple'],
        ['Rare', 'blue'],
        ['Commun', 'green'],
    ])('habille le rang %s', (rank, hue) => {
        expect(rankClass(rank)).toContain(hue);
    });

    it('retombe sur le neutre pour Débutant', () => {
        expect(rankClass('Débutant')).toContain('slate');
        expect(rankClass(undefined)).toContain('slate');
    });
});

describe('applicableDimensions', () => {
    it('écarte les dimensions sans données', () => {
        const score = {
            dimensions: [
                { key: 'mounts', applicable: true },
                { key: 'raids', applicable: false },
            ],
        };

        expect(applicableDimensions(score).map(d => d.key)).toEqual(['mounts']);
    });

    it('supporte un score absent', () => {
        expect(applicableDimensions(null)).toEqual([]);
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
