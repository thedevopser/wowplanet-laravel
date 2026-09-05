import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import SkeletonLoader from './SkeletonLoader.vue';

describe('SkeletonLoader', () => {
    it('announces the loading state to assistive technology', () => {
        const wrapper = mount(SkeletonLoader);

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.attributes('aria-live')).toBe('polite');
        expect(wrapper.text()).toContain('Chargement en cours...');
    });

    it('renders the card variant by default', () => {
        const wrapper = mount(SkeletonLoader);

        expect(wrapper.find('.card-glass').exists()).toBe(true);
        expect(wrapper.find('.grid').exists()).toBe(false);
    });

    it('renders as many placeholders as requested in the grid variant', () => {
        const wrapper = mount(SkeletonLoader, { props: { variant: 'grid', count: 3 } });

        expect(wrapper.findAll('.bg-slate-800\\/40')).toHaveLength(3);
    });

    it('falls back to eight placeholders in the grid variant', () => {
        const wrapper = mount(SkeletonLoader, { props: { variant: 'grid' } });

        expect(wrapper.findAll('.bg-slate-800\\/40')).toHaveLength(8);
    });

    it('renders a tab bar in the tabs variant', () => {
        const wrapper = mount(SkeletonLoader, { props: { variant: 'tabs' } });

        expect(wrapper.findAll('.rounded-t-xl')).toHaveLength(6);
    });

    it('renders three steps in the podium variant', () => {
        const wrapper = mount(SkeletonLoader, { props: { variant: 'podium' } });

        expect(wrapper.findAll('.card-glass')).toHaveLength(3);
    });

    it('renders only the loading label for an unknown variant', () => {
        const wrapper = mount(SkeletonLoader, { props: { variant: 'unknown' } });

        expect(wrapper.find('.card-glass').exists()).toBe(false);
        expect(wrapper.text()).toContain('Chargement en cours...');
    });
});
