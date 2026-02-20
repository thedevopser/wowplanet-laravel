import { describe, it, expect } from 'vitest';
import PrivacyPage from './PrivacyPage.vue';
import { mountWithPlugins } from '../tests/helpers';

describe('PrivacyPage', () => {
    it('renders the privacy heading', async () => {
        const wrapper = await mountWithPlugins(PrivacyPage);

        expect(wrapper.find('h1').text()).toContain('Politique de confidentialité');
    });

    it('contains RGPD rights section', async () => {
        const wrapper = await mountWithPlugins(PrivacyPage);
        const text = wrapper.text();

        expect(text).toContain('Vos droits (RGPD)');
        expect(text).toContain("Droit d'accès");
        expect(text).toContain('Droit de suppression');
    });
});
