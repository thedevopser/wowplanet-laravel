import { describe, it, expect } from 'vitest';
import CguPage from './CguPage.vue';
import { mountWithPlugins } from '../tests/helpers';

describe('CguPage', () => {
    it('renders the CGU heading', async () => {
        const wrapper = await mountWithPlugins(CguPage);

        expect(wrapper.find('h1').text()).toContain("Conditions Générales d'Utilisation");
    });

    it('contains key legal sections', async () => {
        const wrapper = await mountWithPlugins(CguPage);
        const text = wrapper.text();

        expect(text).toContain('Objet');
        expect(text).toContain('Description du service');
        expect(text).toContain('Propriété intellectuelle');
        expect(text).toContain('Droit applicable');
    });
});
