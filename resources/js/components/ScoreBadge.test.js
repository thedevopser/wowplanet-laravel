import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ScoreBadge from './ScoreBadge.vue';

const CIRCUMFERENCE = 2 * Math.PI * 42;

const progressCircle = wrapper => wrapper.findAll('circle')[1];

describe('ScoreBadge', () => {
    it('rounds the displayed score', () => {
        const wrapper = mount(ScoreBadge, { props: { score: 67.6 } });

        expect(wrapper.text()).toContain('68');
    });

    it('shows zero by default', () => {
        const wrapper = mount(ScoreBadge);

        expect(wrapper.text()).toContain('0');
    });

    it('leaves the ring empty at zero', () => {
        const wrapper = mount(ScoreBadge, { props: { score: 0 } });

        expect(progressCircle(wrapper).attributes('stroke-dashoffset')).toBe(String(CIRCUMFERENCE));
    });

    it('fills the ring completely at a hundred', () => {
        const wrapper = mount(ScoreBadge, { props: { score: 100 } });

        expect(Number(progressCircle(wrapper).attributes('stroke-dashoffset'))).toBeCloseTo(0);
    });

    it('fills half the ring at fifty', () => {
        const wrapper = mount(ScoreBadge, { props: { score: 50 } });

        expect(Number(progressCircle(wrapper).attributes('stroke-dashoffset'))).toBeCloseTo(CIRCUMFERENCE / 2);
    });

    it('colors the ring by score bracket', () => {
        const brackets = [
            [80, '#22c55e'],
            [60, '#eab308'],
            [30, '#f97316'],
            [10, '#ef4444'],
        ];

        for (const [score, color] of brackets) {
            const wrapper = mount(ScoreBadge, { props: { score } });

            expect(progressCircle(wrapper).attributes('stroke')).toBe(color);
        }
    });
});
