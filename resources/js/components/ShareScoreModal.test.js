import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ShareScoreModal from './ShareScoreModal.vue';

vi.mock('../utils/scoreCardRenderer', () => ({
    renderScoreCard: vi.fn(() => ({
        width: 700,
        height: 430,
    })),
}));

describe('ShareScoreModal', () => {
    const defaultProps = {
        show: true,
        variant: 'personal',
        scoreData: { globalScore: 75, rank: 'Epique' },
    };

    function mountModal(props = {}) {
        return mount(ShareScoreModal, {
            props: { ...defaultProps, ...props },
            global: {
                stubs: { Teleport: true },
            },
        });
    }

    it('does not render when show=false', () => {
        const wrapper = mountModal({ show: false });

        expect(wrapper.find('.fixed').exists()).toBe(false);
    });

    it('renders modal content when show=true', () => {
        const wrapper = mountModal({ show: true });

        expect(wrapper.find('.fixed').exists()).toBe(true);
        expect(wrapper.find('canvas').exists()).toBe(true);
    });

    it('shows personal title for variant="personal"', () => {
        const wrapper = mountModal({ variant: 'personal' });

        expect(wrapper.text()).toContain('Partager mon score');
    });

    it('shows account title for variant="account"', () => {
        const wrapper = mountModal({ variant: 'account' });

        expect(wrapper.text()).toContain('Partager le score compte');
    });

    it('emits close when backdrop is clicked', async () => {
        const wrapper = mountModal();
        const backdrop = wrapper.find('.bg-black\\/70');

        await backdrop.trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('emits close when close button is clicked', async () => {
        const wrapper = mountModal();
        const closeButton = wrapper.find('button');

        await closeButton.trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('has download and copy buttons', () => {
        const wrapper = mountModal();
        const buttons = wrapper.findAll('button');

        // close button + download + copy = 3 buttons
        expect(buttons.length).toBe(3);
        expect(wrapper.text()).toContain('Telecharger');
        expect(wrapper.text()).toContain("Copier l'image");
    });
});
