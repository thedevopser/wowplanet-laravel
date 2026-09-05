import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import DatabasePageHeader from './DatabasePageHeader.vue';

describe('DatabasePageHeader', () => {
    it('renders the title as the page heading', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Montures' } });

        expect(wrapper.find('h1').text()).toContain('Montures');
    });

    it('renders the subtitle and the count label', () => {
        const wrapper = mount(DatabasePageHeader, {
            props: { title: 'Montures', subtitle: 'Toutes les montures du jeu', count: 42, countLabel: 'montures' },
        });

        expect(wrapper.text()).toContain('Toutes les montures du jeu');
        expect(wrapper.text()).toContain('montures');
    });

    it('formats the count in French', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Quêtes', count: 12345 } });

        expect(wrapper.text()).toContain((12345).toLocaleString('fr-FR'));
    });

    it('shows a zero count by default', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Quêtes' } });

        expect(wrapper.text()).toContain('0');
    });

    it('applies the requested accent color', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Hauts-faits', accentColor: 'amber' } });

        expect(wrapper.html()).toContain('bg-amber-500');
        expect(wrapper.html()).toContain('text-amber-400');
    });

    it('falls back to blue when the accent color is unknown', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Hauts-faits', accentColor: 'fuchsia' } });

        expect(wrapper.html()).toContain('bg-blue-500');
        expect(wrapper.html()).toContain('text-blue-400');
    });

    it('is blue by default', () => {
        const wrapper = mount(DatabasePageHeader, { props: { title: 'Hauts-faits' } });

        expect(wrapper.html()).toContain('bg-blue-600/5');
    });
});
