import { describe, it, expect, beforeEach } from 'vitest';
import RaidsTab from './RaidsTab.vue';
import { mountWithPlugins } from '../tests/helpers';

const STORAGE_KEY = 'wowplanet-raids-collapsed';

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

describe('RaidsTab — repli des cadres', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('affiche les difficultés déployées par défaut', async () => {
        const wrapper = await mountTab([makeRaid()]);
        expect(wrapper.find('[data-testid="difficulty-LFR"]').exists()).toBe(true);
    });

    it('masque les difficultés après un clic sur l\'en-tête', async () => {
        const wrapper = await mountTab([makeRaid()]);

        await wrapper.find('[data-testid="raid-toggle-1307"]').trigger('click');

        expect(wrapper.find('[data-testid="difficulty-LFR"]').exists()).toBe(false);
    });

    it('redéploie les difficultés à un second clic', async () => {
        const wrapper = await mountTab([makeRaid()]);
        const toggle = wrapper.find('[data-testid="raid-toggle-1307"]');

        await toggle.trigger('click');
        await toggle.trigger('click');

        expect(wrapper.find('[data-testid="difficulty-LFR"]').exists()).toBe(true);
    });

    it('replie chaque raid indépendamment des autres', async () => {
        const wrapper = await mountTab([
            makeRaid(),
            makeRaid({ instance_id: 1305, instance_name: 'Sporefall' }),
        ]);

        await wrapper.find('[data-testid="raid-toggle-1307"]').trigger('click');

        expect(wrapper.find('[data-testid="raid-body-1307"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="raid-body-1305"]').exists()).toBe(true);
    });

    it('résume la progression des difficultés entamées quand le cadre est replié', async () => {
        const wrapper = await mountTab([makeRaid()]);

        await wrapper.find('[data-testid="raid-toggle-1307"]').trigger('click');

        const summary = wrapper.find('[data-testid="raid-summary-1307"]');
        expect(summary.text()).toContain('LFR 6/6');
        expect(summary.text()).toContain('M 3/6');
    });

    it('omet du résumé les difficultés non entamées', async () => {
        const wrapper = await mountTab([makeRaid()]);

        await wrapper.find('[data-testid="raid-toggle-1307"]').trigger('click');

        const summary = wrapper.find('[data-testid="raid-summary-1307"]');
        expect(summary.text()).not.toContain('Normal');
        expect(summary.text()).not.toContain('H ');
    });

    it('ne montre aucun résumé tant que le cadre est déployé', async () => {
        const wrapper = await mountTab([makeRaid()]);
        expect(wrapper.find('[data-testid="raid-summary-1307"]').exists()).toBe(false);
    });

    it('persiste les raids repliés dans le localStorage', async () => {
        const wrapper = await mountTab([makeRaid()]);

        await wrapper.find('[data-testid="raid-toggle-1307"]').trigger('click');

        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual([1307]);
    });

    it('retire du localStorage un raid redéployé', async () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([1307]));
        const wrapper = await mountTab([makeRaid()]);

        await wrapper.find('[data-testid="raid-toggle-1307"]').trigger('click');

        expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual([]);
    });

    it('restaure au montage les raids repliés lors de la visite précédente', async () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([1307]));

        const wrapper = await mountTab([
            makeRaid(),
            makeRaid({ instance_id: 1305, instance_name: 'Sporefall' }),
        ]);

        expect(wrapper.find('[data-testid="raid-body-1307"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="raid-body-1305"]').exists()).toBe(true);
    });

    it('ignore un localStorage corrompu et déploie tout', async () => {
        localStorage.setItem(STORAGE_KEY, 'pas du json');

        const wrapper = await mountTab([makeRaid()]);

        expect(wrapper.find('[data-testid="raid-body-1307"]').exists()).toBe(true);
    });

    it('expose l\'état du repli aux lecteurs d\'écran', async () => {
        const wrapper = await mountTab([makeRaid()]);
        const toggle = wrapper.find('[data-testid="raid-toggle-1307"]');
        expect(toggle.attributes('aria-expanded')).toBe('true');

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('false');
    });
});
