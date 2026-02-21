import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import SearchFilter from './SearchFilter.vue';

describe('SearchFilter', () => {
    it('renders with custom placeholder', () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false, placeholder: 'Chercher un mount...' },
        });

        expect(wrapper.find('input[type="text"]').attributes('placeholder')).toBe('Chercher un mount...');
    });

    it('renders with default placeholder when not specified', () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false },
        });

        expect(wrapper.find('input[type="text"]').attributes('placeholder')).toBe('Rechercher...');
    });

    it('emits update:search on input', async () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false },
        });

        const input = wrapper.find('input[type="text"]');
        await input.setValue('dragon');

        expect(wrapper.emitted('update:search')).toBeTruthy();
        expect(wrapper.emitted('update:search')[0]).toEqual(['dragon']);
    });

    it('emits update:hideCompleted on toggle button click', async () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false },
        });

        const toggleBtn = wrapper.findAll('button').find(b => b.text().includes('Masquer'));
        await toggleBtn.trigger('click');

        expect(wrapper.emitted('update:hideCompleted')).toBeTruthy();
        expect(wrapper.emitted('update:hideCompleted')[0]).toEqual([true]);
    });

    it('emits false when toggle button is clicked while active', async () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: true },
        });

        const toggleBtn = wrapper.findAll('button').find(b => b.text().includes('Masquer'));
        await toggleBtn.trigger('click');

        expect(wrapper.emitted('update:hideCompleted')[0]).toEqual([false]);
    });

    it('shows clear button only when search has value', async () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false },
        });

        // Only the toggle button exists, no clear button
        expect(wrapper.findAll('button').length).toBe(1);

        // Clear button appears when search has value
        await wrapper.setProps({ search: 'test' });
        expect(wrapper.findAll('button').length).toBe(2);
    });

    it('emits empty search when clear button is clicked', async () => {
        const wrapper = mount(SearchFilter, {
            props: { search: 'something', hideCompleted: false },
        });

        // Clear button is the one inside the input wrapper (first button)
        const clearBtn = wrapper.findAll('button')[0];
        await clearBtn.trigger('click');

        expect(wrapper.emitted('update:search')).toBeTruthy();
        expect(wrapper.emitted('update:search')[0]).toEqual(['']);
    });

    it('renders custom hide label', () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false, hideLabel: 'Masquer obtenues' },
        });

        expect(wrapper.text()).toContain('Masquer obtenues');
    });

    it('renders default hide label when not specified', () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false },
        });

        expect(wrapper.text()).toContain('Masquer complétés');
    });

    it('applies active styling when hideCompleted is true', () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: true },
        });

        const toggleBtn = wrapper.findAll('button').find(b => b.text().includes('Masquer'));
        expect(toggleBtn.classes()).toContain('text-blue-400');
    });

    it('applies inactive styling when hideCompleted is false', () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false },
        });

        const toggleBtn = wrapper.findAll('button').find(b => b.text().includes('Masquer'));
        expect(toggleBtn.classes()).toContain('text-slate-500');
    });

    it('renders extra-toggles slot content', () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false },
            slots: {
                'extra-toggles': '<button class="extra-toggle">Extra</button>',
            },
        });

        expect(wrapper.find('.extra-toggle').exists()).toBe(true);
        expect(wrapper.find('.extra-toggle').text()).toBe('Extra');
    });
});
