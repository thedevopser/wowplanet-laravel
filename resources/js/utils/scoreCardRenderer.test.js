import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderScoreCard } from './scoreCardRenderer';

const mockCtx = {
    fillRect: vi.fn(),
    fillText: vi.fn(),
    fill: vi.fn(),
    stroke: vi.fn(),
    beginPath: vi.fn(),
    moveTo: vi.fn(),
    arcTo: vi.fn(),
    closePath: vi.fn(),
    roundRect: vi.fn(),
    measureText: vi.fn(() => ({ width: 50 })),
    createLinearGradient: vi.fn(() => ({ addColorStop: vi.fn() })),
    set fillStyle(_) {},
    set strokeStyle(_) {},
    set lineWidth(_) {},
    set font(_) {},
    set textBaseline(_) {},
    set textAlign(_) {},
};

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('document', {
        createElement: vi.fn((tag) => {
            if (tag === 'canvas') {
                return { width: 0, height: 0, getContext: () => mockCtx };
            }
            return {};
        }),
    });
});

const dimension = (key, label, completed, total, score, applicable = true) =>
    ({ key, label, completed, total, score, applicable, weight: 0.1 });

// Sept dimensions notées : la carte doit garder sa hauteur historique de 430 px.
const baseDimensions = [
    dimension('quests', 'Quêtes', 50, 100, 50),
    dimension('achievements', 'Hauts-faits', 30, 100, 30),
    dimension('reputations', 'Réputations', 10, 20, 50),
    dimension('mounts', 'Montures', 2, 3, 66.7),
    dimension('pets', 'Mascottes', 1, 2, 50),
    dimension('decor', 'Décorations', 1, 1, 100),
    dimension('professions', 'Métiers', 5, 10, 50),
];

describe('renderScoreCard', () => {
    it('returns a canvas element with width=700 and height=430', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'TestChar',
            characterRealm: 'Hyjal',
            characterClass: 'Guerrier',
            characterRace: 'Humain',
            characterLevel: 80,
            classId: 1,
            globalScore: 52,
            rank: 'Gold',
            dimensions: baseDimensions,
        });

        expect(canvas).toBeDefined();
        expect(canvas.width).toBe(700);
        expect(canvas.height).toBe(430);
    });

    it('handles personal variant', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'MyHero',
            characterRealm: 'Archimonde',
            characterClass: 'Mage',
            characterRace: 'Elfe de sang',
            characterLevel: 80,
            classId: 8,
            globalScore: 75,
            rank: 'Platine',
            dimensions: baseDimensions,
        });

        expect(canvas).toBeDefined();
        expect(canvas.width).toBe(700);
        // Verify fillText was called (for character name, score, etc.)
        expect(mockCtx.fillText).toHaveBeenCalled();
    });

    it('handles account variant', () => {
        const canvas = renderScoreCard({
            variant: 'account',
            characterCount: 5,
            globalScore: 60,
            rank: 'Or',
            dimensions: baseDimensions,
        });

        expect(canvas).toBeDefined();
        expect(canvas.width).toBe(700);
        expect(mockCtx.fillText).toHaveBeenCalled();
    });

    it('grows by one row per extra dimension', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'TestChar',
            globalScore: 52,
            rank: 'Or',
            dimensions: [
                ...baseDimensions,
                dimension('transmog', 'Garde-robe', 300, 1000, 30),
                dimension('raids', 'Raids', 4, 8, 50),
            ],
        });

        expect(canvas.height).toBe(490);
    });

    it('ignores non-applicable dimensions', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'TestChar',
            globalScore: 52,
            rank: 'Or',
            dimensions: [
                ...baseDimensions,
                dimension('raids', 'Raids', 0, 0, 0, false),
            ],
        });

        expect(canvas.height).toBe(430);
    });

    it('handles missing dimensions gracefully', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            characterName: 'TestChar',
            characterRealm: 'Hyjal',
            characterClass: 'Guerrier',
            characterRace: 'Humain',
            characterLevel: 80,
            classId: 1,
            globalScore: 0,
            rank: 'Bronze',
            dimensions: undefined,
        });

        expect(canvas).toBeDefined();
        expect(canvas.width).toBe(700);
        expect(canvas.height).toBe(220);
    });

    it('handles missing character info gracefully', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            globalScore: 0,
            rank: '',
            dimensions: [],
        });

        expect(canvas).toBeDefined();
        expect(canvas.width).toBe(700);
    });

    it('calls createLinearGradient for background', () => {
        renderScoreCard({
            variant: 'personal',
            characterName: 'Test',
            globalScore: 50,
            rank: 'Silver',
            dimensions: baseDimensions,
        });

        expect(mockCtx.createLinearGradient).toHaveBeenCalledWith(0, 0, 700, 430);
    });
});
