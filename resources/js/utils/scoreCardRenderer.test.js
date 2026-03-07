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

const baseDimensions = {
    quests: { completed: 50, total: 100, score: 50 },
    achievements: { completed: 30, total: 100, score: 30 },
    reputations: { completed: 10, total: 20, score: 50 },
    mounts: { completed: 2, total: 3, score: 66.7 },
    pets: { completed: 1, total: 2, score: 50 },
    decor: { completed: 1, total: 1, score: 100 },
    professions: { completed: 5, total: 10, score: 50 },
};

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
        expect(canvas.height).toBe(430);
    });

    it('handles missing character info gracefully', () => {
        const canvas = renderScoreCard({
            variant: 'personal',
            globalScore: 0,
            rank: '',
            dimensions: {},
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
