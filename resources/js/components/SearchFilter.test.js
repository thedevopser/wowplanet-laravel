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

    it('emits update:hideCompleted on checkbox change', async () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false },
        });

        const checkbox = wrapper.find('input[type="checkbox"]');
        await checkbox.setValue(true);

        expect(wrapper.emitted('update:hideCompleted')).toBeTruthy();
        expect(wrapper.emitted('update:hideCompleted')[0]).toEqual([true]);
    });

    it('shows clear button only when search has value', async () => {
        const wrapper = mount(SearchFilter, {
            props: { search: '', hideCompleted: false },
        });

        // No clear button when search is empty
        expect(wrapper.findAll('button').length).toBe(0);

        // Clear button appears when search has value
        await wrapper.setProps({ search: 'test' });
        const clearBtn = wrapper.find('button');
        expect(clearBtn.exists()).toBe(true);
    });

    it('emits empty search when clear button is clicked', async () => {
        const wrapper = mount(SearchFilter, {
            props: { search: 'something', hideCompleted: false },
        });

        const clearBtn = wrapper.find('button');
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
});
