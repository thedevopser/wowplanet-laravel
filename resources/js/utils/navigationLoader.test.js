import { describe, it, expect, afterEach } from 'vitest';
import { showNavigationLoader, hideNavigationLoader } from './navigationLoader';

const OVERLAY_ID = 'wp-nav-loader';

describe('navigationLoader', () => {
    afterEach(() => {
        document.getElementById(OVERLAY_ID)?.remove();
    });

    it('injects the overlay with the default hint', () => {
        showNavigationLoader();

        const overlay = document.getElementById(OVERLAY_ID);
        expect(overlay).not.toBeNull();
        expect(overlay.textContent).toContain('Synchronisation en cours');
    });

    it('supports a custom hint', () => {
        showNavigationLoader('Un indice personnalisé');

        expect(document.getElementById(OVERLAY_ID).textContent).toContain('Un indice personnalisé');
    });

    it('does not inject a second overlay if one already exists', () => {
        showNavigationLoader();
        showNavigationLoader();

        expect(document.querySelectorAll(`#${OVERLAY_ID}`)).toHaveLength(1);
    });

    it('removes the overlay on hide', () => {
        showNavigationLoader();
        hideNavigationLoader();

        expect(document.getElementById(OVERLAY_ID)).toBeNull();
    });

    it('hide is a no-op when no overlay is present', () => {
        expect(() => hideNavigationLoader()).not.toThrow();
    });
});
