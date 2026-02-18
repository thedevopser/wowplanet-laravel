import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LoadingSpinner from './LoadingSpinner.vue';

describe('LoadingSpinner', () => {
    it('renders with default props', () => {
        const wrapper = mount(LoadingSpinner);

        expect(wrapper.text()).toContain('Synchronisation en cours...');
        expect(wrapper.text()).toContain("Analyse de votre personnage via l'API Blizzard");
    });

    it('renders custom title and subtitle', () => {
        const wrapper = mount(LoadingSpinner, {
            props: {
                title: 'Chargement...',
                subtitle: 'Veuillez patienter',
            },
        });

        expect(wrapper.text()).toContain('Chargement...');
        expect(wrapper.text()).toContain('Veuillez patienter');
    });

    it('renders custom icon', () => {
        const wrapper = mount(LoadingSpinner, {
            props: { icon: '🔮' },
        });

        expect(wrapper.text()).toContain('🔮');
    });

    it('shows hint when provided', () => {
        const wrapper = mount(LoadingSpinner, {
            props: { hint: 'Cela peut prendre un moment' },
        });

        expect(wrapper.text()).toContain('Cela peut prendre un moment');
    });

    it('does not show hint when empty', () => {
        const wrapper = mount(LoadingSpinner);
        const hintElement = wrapper.find('[class*="text-xs mt-6"]');

        expect(hintElement.exists()).toBe(false);
    });
});
