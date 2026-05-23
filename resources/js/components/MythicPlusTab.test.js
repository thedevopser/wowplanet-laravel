import { describe, it, expect } from 'vitest';
import MythicPlusTab from './MythicPlusTab.vue';
import { mountWithPlugins } from '../tests/helpers';

const baseMember = { name: 'Player1', realm: 'Dalaran', spec: 'Fury', ilvl: 480 };

function makeRun(overrides = {}) {
    return {
        dungeon_id: 1,
        dungeon_name: 'Ara-Kara',
        level: 12,
        is_timed: true,
        map_score: 185.5,
        map_score_color: { r: 163, g: 53, b: 238 },
        duration_ms: 1920000,
        completed_at: 1709251200000,
        members: [baseMember],
        ...overrides,
    };
}

const mythicData = {
    season_id: 13,
    rating: 2450.5,
    rating_color: { r: 255, g: 128, b: 0 },
    best_runs: [
        makeRun(),
        makeRun({ dungeon_id: 2, dungeon_name: 'Stonevault', level: 11, is_timed: false, map_score: 150 }),
    ],
};

function mountTab(mythicKeystone) {
    return mountWithPlugins(MythicPlusTab, {
        initialState: { character: { character: { mythicKeystone } } },
    });
}

describe('MythicPlusTab', () => {
    it('shows empty state when no mythic data', async () => {
        const wrapper = await mountTab(null);
        expect(wrapper.text()).toContain('Aucune donnée Mythique+');
    });

    it('shows rating and season info when data exists', async () => {
        const wrapper = await mountTab(mythicData);
        expect(wrapper.text()).toContain('Score Mythique+');
        expect(wrapper.text()).toContain('Saison 13');
        expect(wrapper.text()).toContain('2451');
    });

    it('shows dungeon cards with level', async () => {
        const wrapper = await mountTab(mythicData);
        expect(wrapper.text()).toContain('Ara-Kara');
        expect(wrapper.text()).toContain('+12');
    });

    it('formats duration correctly', async () => {
        const wrapper = await mountTab(mythicData);
        expect(wrapper.text()).toContain('32:00');
    });

    it('shows "Aucune course" when best_runs is empty', async () => {
        const wrapper = await mountTab({ ...mythicData, best_runs: [] });
        expect(wrapper.text()).toContain('Aucune course enregistrée cette saison');
    });

    it('displays member info in group composition', async () => {
        const wrapper = await mountTab(mythicData);
        expect(wrapper.text()).toContain('Player1');
        expect(wrapper.text()).toContain('Fury');
        expect(wrapper.text()).toContain('480');
    });

    it('applies rating color style', async () => {
        const wrapper = await mountTab(mythicData);
        const ratingEl = wrapper.find('[style*="rgb(255, 128, 0)"]');
        expect(ratingEl.exists()).toBe(true);
    });

    describe('two-column layout', () => {
        it('shows timed runs under "Dans les temps" column', async () => {
            const wrapper = await mountTab(mythicData);
            expect(wrapper.text()).toContain('Dans les temps');
            const timedColumn = wrapper.find('[data-testid="timed-column"]');
            expect(timedColumn.exists()).toBe(true);
            expect(timedColumn.text()).toContain('Ara-Kara');
        });

        it('shows untimed runs under "Hors temps" column', async () => {
            const wrapper = await mountTab(mythicData);
            expect(wrapper.text()).toContain('Hors temps');
            const untimedColumn = wrapper.find('[data-testid="untimed-column"]');
            expect(untimedColumn.exists()).toBe(true);
            expect(untimedColumn.text()).toContain('Stonevault');
        });

        it('deduplicates timed runs keeping highest level', async () => {
            const data = {
                ...mythicData,
                best_runs: [
                    makeRun({ level: 15, map_score: 200 }),
                    makeRun({ level: 10, map_score: 160 }),
                ],
            };
            const wrapper = await mountTab(data);
            const timedColumn = wrapper.find('[data-testid="timed-column"]');
            expect(timedColumn.text()).toContain('+15');
            expect(timedColumn.text()).not.toContain('+10');
        });

        it('deduplicates untimed runs keeping highest level', async () => {
            const data = {
                ...mythicData,
                best_runs: [
                    makeRun({ dungeon_id: 2, dungeon_name: 'Stonevault', level: 14, is_timed: false }),
                    makeRun({ dungeon_id: 2, dungeon_name: 'Stonevault', level: 11, is_timed: false }),
                ],
            };
            const wrapper = await mountTab(data);
            const untimedColumn = wrapper.find('[data-testid="untimed-column"]');
            expect(untimedColumn.text()).toContain('+14');
            expect(untimedColumn.text()).not.toContain('+11');
        });

        it('allows same dungeon in both columns', async () => {
            const data = {
                ...mythicData,
                best_runs: [
                    makeRun({ level: 15, is_timed: true }),
                    makeRun({ level: 10, is_timed: false }),
                ],
            };
            const wrapper = await mountTab(data);
            const timedColumn = wrapper.find('[data-testid="timed-column"]');
            const untimedColumn = wrapper.find('[data-testid="untimed-column"]');
            expect(timedColumn.text()).toContain('Ara-Kara');
            expect(untimedColumn.text()).toContain('Ara-Kara');
        });

        it('shows empty message when no timed runs exist', async () => {
            const data = {
                ...mythicData,
                best_runs: [makeRun({ is_timed: false })],
            };
            const wrapper = await mountTab(data);
            const timedColumn = wrapper.find('[data-testid="timed-column"]');
            expect(timedColumn.text()).toContain('Aucune course dans les temps');
        });

        it('shows empty message when no untimed runs exist', async () => {
            const data = {
                ...mythicData,
                best_runs: [makeRun({ is_timed: true })],
            };
            const wrapper = await mountTab(data);
            const untimedColumn = wrapper.find('[data-testid="untimed-column"]');
            expect(untimedColumn.text()).toContain('Aucune course hors temps');
        });

        it('shows unique dungeon count in header', async () => {
            const data = {
                ...mythicData,
                best_runs: [
                    makeRun({ dungeon_id: 1, is_timed: true }),
                    makeRun({ dungeon_id: 1, is_timed: false }),
                    makeRun({ dungeon_id: 2, dungeon_name: 'Stonevault', is_timed: true }),
                ],
            };
            const wrapper = await mountTab(data);
            expect(wrapper.text()).toContain('2 donjons');
        });
    });
});
