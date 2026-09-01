import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ScoreRadar from './ScoreRadar.vue';

const axes = (count) =>
    Array.from({ length: count }, (_, i) => ({ label: `Axe ${i + 1}`, score: 50 }));

const mountRadar = (props = {}) => mount(ScoreRadar, { props: { axes: axes(9), ...props } });

describe('ScoreRadar', () => {
    it('trace une ligne, un point et un libellé par axe', () => {
        const wrapper = mountRadar();

        expect(wrapper.findAll('line')).toHaveLength(9);
        expect(wrapper.findAll('circle')).toHaveLength(9);
        expect(wrapper.text()).toContain('Axe 9');
    });

    it('redistribue les axes quand une dimension sort du calcul', () => {
        const wrapper = mountRadar({ axes: axes(8) });

        expect(wrapper.findAll('circle')).toHaveLength(8);
        expect(wrapper.text()).not.toContain('Axe 9');
    });

    it('dessine les quatre paliers de la grille', () => {
        // Quatre polygones de grille + un polygone de données.
        expect(mountRadar().findAll('polygon')).toHaveLength(5);
    });

    it('affiche le pourcentage arrondi de chaque axe', () => {
        const wrapper = mountRadar({ axes: [{ label: 'Raids', score: 66.66 }] });

        expect(wrapper.text()).toContain('67%');
    });

    it('place le premier axe au sommet du cercle', () => {
        const wrapper = mountRadar({ axes: [{ label: 'Quêtes', score: 100 }], size: 200 });

        // Centre 100, rayon 100 − 45 : le point à 100 % est droit au-dessus du centre.
        const point = wrapper.find('circle');
        expect(Number(point.attributes('cx'))).toBeCloseTo(100);
        expect(Number(point.attributes('cy'))).toBeCloseTo(45);
    });

    it('rapproche le polygone du centre quand les scores sont bas', () => {
        const low = mountRadar({ axes: [{ label: 'A', score: 10 }], size: 200 });
        const high = mountRadar({ axes: [{ label: 'A', score: 90 }], size: 200 });

        expect(Number(low.find('circle').attributes('cy')))
            .toBeGreaterThan(Number(high.find('circle').attributes('cy')));
    });

    it('applique la couleur fournie pour chaque axe', () => {
        const wrapper = mountRadar({ axes: axes(2), colors: ['#f43f5e', '#a78bfa'] });

        expect(wrapper.findAll('circle').map(c => c.attributes('fill')))
            .toEqual(['#f43f5e', '#a78bfa']);
    });

    it('retombe sur la couleur courante sans palette', () => {
        expect(mountRadar({ axes: axes(1) }).find('circle').attributes('fill')).toBe('currentColor');
    });

    it('aligne les libellés selon leur côté du cercle', () => {
        const wrapper = mountRadar({ axes: axes(4) });
        const anchors = wrapper.findAll('text').slice(0, 4).map(t => t.attributes('text-anchor'));

        // Haut et bas centrés, droite et gauche accrochés vers l'extérieur.
        expect(anchors).toEqual(['middle', 'start', 'middle', 'end']);
    });

    it('dimensionne le conteneur selon la taille demandée', () => {
        const wrapper = mountRadar({ size: 280 });

        expect(wrapper.attributes('style')).toContain('280px');
    });
});
