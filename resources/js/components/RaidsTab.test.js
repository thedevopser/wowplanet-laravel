import { describe, it, expect } from 'vitest';
import RaidsTab from './RaidsTab.vue';
import { mountWithPlugins } from '../tests/helpers';

function makeRaid(overrides = {}) {
    return {
        instance_id: 1307,
        instance_name: 'The Voidspire',
        modes: [
            {
                difficulty_type: 'LFR',
                difficulty_label: 'LFR',
                completed_count: 6,
                total_count: 6,
                encounters: [
                    { id: 2733, name: 'Imperator Averzian', last_kill_timestamp: 1775411018000 },
                ],
            },
            {
                difficulty_type: 'MYTHIC',
                difficulty_label: 'Mythique',
                completed_count: 3,
                total_count: 6,
                encounters: [
                    { id: 2733, name: 'Imperator Averzian', last_kill_timestamp: 1775411018000 },
                ],
            },
        ],
        ...overrides,
    };
}

function mountTab(raids) {
    return mountWithPlugins(RaidsTab, {
        initialState: { character: { character: { raids } } },
    });
}

describe('RaidsTab', () => {
    it('shows empty state when raids is null', async () => {
        const wrapper = await mountTab(null);
        expect(wrapper.text()).toContain('Aucune progression de raid');
    });

    it('renders the raid name', async () => {
        const wrapper = await mountTab([makeRaid()]);
        expect(wrapper.text()).toContain('The Voidspire');
    });

    it('renders a completion counter per difficulty', async () => {
        const wrapper = await mountTab([makeRaid()]);
        expect(wrapper.text()).toContain('Mythique');
        expect(wrapper.text()).toContain('3/6');
        expect(wrapper.text()).toContain('LFR');
        expect(wrapper.text()).toContain('6/6');
    });

    it('lists defeated bosses', async () => {
        const wrapper = await mountTab([makeRaid()]);
        expect(wrapper.text()).toContain('Imperator Averzian');
    });

    it('renders multiple raids', async () => {
        const wrapper = await mountTab([
            makeRaid(),
            makeRaid({ instance_id: 1305, instance_name: 'Sporefall', modes: [] }),
        ]);
        expect(wrapper.text()).toContain('The Voidspire');
        expect(wrapper.text()).toContain('Sporefall');
    });
});
