import { describe, it, expect } from 'vitest';
import MythicPlusTab from './MythicPlusTab.vue';
import { mountWithPlugins } from '../tests/helpers';

const mythicData = {
    season_id: 13,
    rating: 2450.5,
    rating_color: { r: 255, g: 128, b: 0 },
    best_runs: [
        {
            dungeon_id: 1,
            dungeon_name: 'Ara-Kara',
            level: 12,
            is_timed: true,
            map_score: 185.5,
            map_score_color: { r: 163, g: 53, b: 238 },
            duration_ms: 1920000,
            completed_at: 1709251200000,
            members: [
                { name: 'Player1', realm: 'Dalaran', spec: 'Fury', ilvl: 480 },
            ],
        },
    ],
};

describe('MythicPlusTab', () => {
    it('shows empty state when no mythic data', async () => {
        const wrapper = await mountWithPlugins(MythicPlusTab, {
            initialState: { character: { character: { mythicKeystone: null } } },
        });

        expect(wrapper.text()).toContain('Aucune donnée Mythique+');
    });

    it('shows rating and season info when data exists', async () => {
        const wrapper = await mountWithPlugins(MythicPlusTab, {
            initialState: { character: { character: { mythicKeystone: mythicData } } },
        });

        expect(wrapper.text()).toContain('Score Mythique+');
        expect(wrapper.text()).toContain('Saison 13');
        expect(wrapper.text()).toContain('2451'); // Math.round(2450.5)
    });

    it('shows dungeon cards with level and timed indicator', async () => {
        const wrapper = await mountWithPlugins(MythicPlusTab, {
            initialState: { character: { character: { mythicKeystone: mythicData } } },
        });

        expect(wrapper.text()).toContain('Ara-Kara');
        expect(wrapper.text()).toContain('+12');
        // Timed checkmark
        expect(wrapper.text()).toContain('\u2713');
    });

    it('shows untimed indicator for untimed runs', async () => {
        const untimedData = {
            ...mythicData,
            best_runs: [{ ...mythicData.best_runs[0], is_timed: false }],
        };
        const wrapper = await mountWithPlugins(MythicPlusTab, {
            initialState: { character: { character: { mythicKeystone: untimedData } } },
        });

        expect(wrapper.text()).toContain('\u2717');
    });

    it('formats duration correctly', async () => {
        const wrapper = await mountWithPlugins(MythicPlusTab, {
            initialState: { character: { character: { mythicKeystone: mythicData } } },
        });

        // 1920000ms = 1920s = 32min 0sec
        expect(wrapper.text()).toContain('32:00');
    });

    it('shows "Aucune course" when best_runs is empty', async () => {
        const noRunsData = { ...mythicData, best_runs: [] };
        const wrapper = await mountWithPlugins(MythicPlusTab, {
            initialState: { character: { character: { mythicKeystone: noRunsData } } },
        });

        expect(wrapper.text()).toContain('Aucune course enregistrée cette saison');
    });

    it('displays member info in group composition', async () => {
        const wrapper = await mountWithPlugins(MythicPlusTab, {
            initialState: { character: { character: { mythicKeystone: mythicData } } },
        });

        expect(wrapper.text()).toContain('Player1');
        expect(wrapper.text()).toContain('Fury');
        expect(wrapper.text()).toContain('480');
    });

    it('applies rating color style', async () => {
        const wrapper = await mountWithPlugins(MythicPlusTab, {
            initialState: { character: { character: { mythicKeystone: mythicData } } },
        });

        const ratingEl = wrapper.find('[style*="rgb(255, 128, 0)"]');
        expect(ratingEl.exists()).toBe(true);
    });
});
