import { describe, it, expect } from 'vitest';
import { classColors } from './classColors';

describe('classColors', () => {
    it('contains all 13 WoW classes', () => {
        expect(Object.keys(classColors)).toHaveLength(13);
    });

    it.each([
        [1, '#C79C6E'],
        [2, '#F58CBA'],
        [3, '#ABD473'],
        [4, '#FFF569'],
        [5, '#FFFFFF'],
        [6, '#C41E3A'],
        [7, '#0070DE'],
        [8, '#69CCF0'],
        [9, '#9482C9'],
        [10, '#00FF96'],
        [11, '#FF7D0A'],
        [12, '#A330C9'],
        [13, '#33937F'],
    ])('class %i has color %s', (classId, expectedColor) => {
        expect(classColors[classId]).toBe(expectedColor);
    });

    it('returns undefined for unknown class id', () => {
        expect(classColors[99]).toBeUndefined();
        expect(classColors[0]).toBeUndefined();
    });
});
