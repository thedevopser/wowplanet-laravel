import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/admin', props: {} }),
    router: { visit: vi.fn(), on: vi.fn() },
}));

import axios from 'axios';
import { mountWithPlugins } from '../tests/helpers';
import AdminPage from './AdminPage.vue';

vi.mock('axios');

const mountAdmin = async (maintenance = false) => {
    axios.get = vi.fn().mockResolvedValue({ data: { maintenance } });

    return mountWithPlugins(AdminPage);
};

const buttonLabelled = (wrapper, label) => wrapper.findAll('button').find(b => b.text() === label);

const clickImport = async (wrapper, label = 'Importer les donnees') => {
    axios.post = vi.fn().mockResolvedValue({ data: { jobId: 'job-1' } });
    await buttonLabelled(wrapper, label).trigger('click');
    await wrapper.vm.$nextTick();
};

beforeEach(() => vi.clearAllMocks());

describe('AdminPage', () => {
    it('renders the admin panel and fetches status on mount', async () => {
        const wrapper = await mountAdmin();

        expect(wrapper.text()).toContain('Administration');
        expect(axios.get).toHaveBeenCalledWith('/api/admin/status');
    });

    it('keeps working when the status request fails', async () => {
        axios.get = vi.fn().mockRejectedValue(new Error('boom'));

        const wrapper = await mountWithPlugins(AdminPage);

        expect(wrapper.text()).toContain('Application en ligne');
    });

    describe('maintenance', () => {
        it('reports the application as online', async () => {
            const wrapper = await mountAdmin(false);

            expect(wrapper.text()).toContain('Application en ligne');
            expect(buttonLabelled(wrapper, 'Activer la maintenance')).toBeDefined();
        });

        it('reports the maintenance mode as active', async () => {
            const wrapper = await mountAdmin(true);

            expect(wrapper.text()).toContain('Mode maintenance actif');
            expect(buttonLabelled(wrapper, 'Desactiver la maintenance')).toBeDefined();
        });

        it('enables the maintenance mode with a generated secret', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { maintenance: true } });

            const wrapper = await mountAdmin(false);
            await buttonLabelled(wrapper, 'Activer la maintenance').trigger('click');
            await wrapper.vm.$nextTick();

            const [url, payload] = axios.post.mock.calls[0];

            expect(url).toBe('/api/admin/maintenance');
            expect(payload.enable).toBe(true);
            expect(payload.secret).toMatch(/^[a-z0-9]{32}$/);
            expect(wrapper.text()).toContain('Mode maintenance actif');
        });

        it('shows the bypass url once maintenance is enabled', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { maintenance: true } });

            const wrapper = await mountAdmin(false);
            await buttonLabelled(wrapper, 'Activer la maintenance').trigger('click');
            await wrapper.vm.$nextTick();

            const secret = axios.post.mock.calls[0][1].secret;

            expect(wrapper.find('a[href$="' + secret + '"]').exists()).toBe(true);
        });

        it('disables the maintenance mode and drops the bypass url', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { maintenance: false } });

            const wrapper = await mountAdmin(true);
            await buttonLabelled(wrapper, 'Desactiver la maintenance').trigger('click');
            await wrapper.vm.$nextTick();

            expect(axios.post).toHaveBeenCalledWith('/api/admin/maintenance', { enable: false, secret: null });
            expect(wrapper.text()).toContain('Application en ligne');
            expect(wrapper.text()).not.toContain('URL de bypass');
        });

        it('leaves the state untouched when the request fails', async () => {
            axios.post = vi.fn().mockRejectedValue(new Error('boom'));

            const wrapper = await mountAdmin(false);
            await buttonLabelled(wrapper, 'Activer la maintenance').trigger('click');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('Application en ligne');
        });
    });

    describe('cache', () => {
        it('shows the output of a successful purge', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { output: 'Cache vidé' } });

            const wrapper = await mountAdmin();
            await buttonLabelled(wrapper, 'Vider les caches').trigger('click');
            await wrapper.vm.$nextTick();

            expect(axios.post).toHaveBeenCalledWith('/api/admin/clear-cache');
            expect(wrapper.text()).toContain('Cache vidé');
        });

        it('shows the server message when the purge fails', async () => {
            axios.post = vi.fn().mockRejectedValue({ response: { data: { message: 'Accès refusé' } } });

            const wrapper = await mountAdmin();
            await buttonLabelled(wrapper, 'Vider les caches').trigger('click');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('Accès refusé');
        });

        it('falls back to a generic message when the failure carries none', async () => {
            axios.post = vi.fn().mockRejectedValue(new Error('boom'));

            const wrapper = await mountAdmin();
            await buttonLabelled(wrapper, 'Vider les caches').trigger('click');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('Erreur');
        });
    });

    describe('imports', () => {
        beforeEach(() => vi.useFakeTimers());
        afterEach(() => vi.useRealTimers());

        it('sends the selected type with the import command', async () => {
            const wrapper = await mountAdmin();
            await wrapper.findAll('select')[0].setValue('mounts');
            await clickImport(wrapper);

            expect(axios.post).toHaveBeenCalledWith('/api/admin/import', { command: 'app:wow-data-import', type: 'mounts' });
        });

        it('sends the selected type with the refresh command', async () => {
            const wrapper = await mountAdmin();
            await wrapper.findAll('select')[1].setValue('quests');
            await clickImport(wrapper, 'Rafraichir les donnees');

            expect(axios.post).toHaveBeenCalledWith('/api/admin/import', { command: 'app:wow-data-refresh', type: 'quests' });
        });

        it('sends no type for the commands that take none', async () => {
            const download = await mountAdmin();
            await clickImport(download, 'Telecharger DB2');
            expect(axios.post).toHaveBeenCalledWith('/api/admin/import', { command: 'app:download-db2' });

            const tag = await mountAdmin();
            await clickImport(tag, 'Tagger factions quetes');
            expect(axios.post).toHaveBeenCalledWith('/api/admin/import', { command: 'app:wow-quest-faction-tag' });
        });

        it('announces the job as pending as soon as it is queued', async () => {
            const wrapper = await mountAdmin();
            await clickImport(wrapper);

            expect(wrapper.text()).toContain('En attente...');
        });

        it('disables the import buttons while a job is running', async () => {
            const wrapper = await mountAdmin();
            await clickImport(wrapper);

            expect(buttonLabelled(wrapper, 'Importer les donnees').attributes('disabled')).toBeDefined();
        });

        it('follows the progress of the job', async () => {
            const wrapper = await mountAdmin();
            await clickImport(wrapper);

            axios.get = vi.fn().mockResolvedValue({ data: { status: 'running', output: 'Import 30%' } });
            await vi.advanceTimersByTimeAsync(3000);

            expect(axios.get).toHaveBeenCalledWith('/api/admin/import/job-1');
            expect(wrapper.text()).toContain('Import en cours...');
            expect(wrapper.text()).toContain('Import 30%');
        });

        it('stops polling and releases the buttons once the job completes', async () => {
            const wrapper = await mountAdmin();
            await clickImport(wrapper);

            axios.get = vi.fn().mockResolvedValue({ data: { status: 'completed', output: 'Termine' } });
            await vi.advanceTimersByTimeAsync(3000);

            expect(wrapper.text()).toContain('Termine');
            expect(buttonLabelled(wrapper, 'Importer les donnees').attributes('disabled')).toBeUndefined();

            await vi.advanceTimersByTimeAsync(9000);
            expect(axios.get).toHaveBeenCalledTimes(1);
        });

        it('stops polling when the job fails', async () => {
            const wrapper = await mountAdmin();
            await clickImport(wrapper);

            axios.get = vi.fn().mockResolvedValue({ data: { status: 'failed' } });
            await vi.advanceTimersByTimeAsync(3000);

            expect(wrapper.text()).toContain('Echec');
            expect(buttonLabelled(wrapper, 'Importer les donnees').attributes('disabled')).toBeUndefined();
        });

        it('keeps the previous output when a poll carries none', async () => {
            const wrapper = await mountAdmin();
            await clickImport(wrapper);

            axios.get = vi.fn()
                .mockResolvedValueOnce({ data: { status: 'running', output: 'Import 30%' } })
                .mockResolvedValueOnce({ data: { status: 'running' } });

            await vi.advanceTimersByTimeAsync(6000);

            expect(wrapper.text()).toContain('Import 30%');
        });

        it('stops polling when a poll request fails', async () => {
            const wrapper = await mountAdmin();
            await clickImport(wrapper);

            axios.get = vi.fn().mockRejectedValue(new Error('boom'));
            await vi.advanceTimersByTimeAsync(3000);

            expect(buttonLabelled(wrapper, 'Importer les donnees').attributes('disabled')).toBeUndefined();

            await vi.advanceTimersByTimeAsync(9000);
            expect(axios.get).toHaveBeenCalledTimes(1);
        });

        it('shows the server message when the job cannot be queued', async () => {
            axios.post = vi.fn().mockRejectedValue({ response: { data: { message: 'Quota Blizzard atteint' } } });

            const wrapper = await mountAdmin();
            await buttonLabelled(wrapper, 'Importer les donnees').trigger('click');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('Quota Blizzard atteint');
            expect(buttonLabelled(wrapper, 'Importer les donnees').attributes('disabled')).toBeUndefined();
        });

        it('falls back to a generic message when the failure carries none', async () => {
            axios.post = vi.fn().mockRejectedValue(new Error('boom'));

            const wrapper = await mountAdmin();
            await buttonLabelled(wrapper, 'Importer les donnees').trigger('click');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('Erreur lors du lancement');
        });

        it('stops polling once the page is left', async () => {
            const wrapper = await mountAdmin();
            await clickImport(wrapper);

            axios.get = vi.fn().mockResolvedValue({ data: { status: 'running' } });
            wrapper.unmount();
            await vi.advanceTimersByTimeAsync(9000);

            expect(axios.get).not.toHaveBeenCalled();
        });
    });

    describe('discord announcement', () => {
        beforeEach(() => vi.useFakeTimers());
        afterEach(() => vi.useRealTimers());

        const fillAnnouncement = async wrapper => {
            await wrapper.findAll('input')[0].setValue('Nouvelle version');
            await wrapper.find('textarea').setValue('**Score** revu');
        };

        it('requires a title and a description before sending', async () => {
            const wrapper = await mountAdmin();

            expect(buttonLabelled(wrapper, 'Envoyer').attributes('disabled')).toBeDefined();

            await fillAnnouncement(wrapper);

            expect(buttonLabelled(wrapper, 'Envoyer').attributes('disabled')).toBeUndefined();
        });

        it('previews the description as rendered markdown', async () => {
            const wrapper = await mountAdmin();
            await fillAnnouncement(wrapper);

            expect(wrapper.find('.discord-markdown').html()).toContain('<strong>Score</strong>');
        });

        it('sends the announcement and clears the form', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

            const wrapper = await mountAdmin();
            await fillAnnouncement(wrapper);
            await buttonLabelled(wrapper, 'Envoyer').trigger('click');
            await wrapper.vm.$nextTick();

            expect(axios.post).toHaveBeenCalledWith('/api/admin/discord', {
                channel: 'changelog',
                title: 'Nouvelle version',
                description: '**Score** revu',
                color: 3447003,
            });
            expect(wrapper.text()).toContain('Envoye avec succes');
            expect(wrapper.findAll('input')[0].element.value).toBe('');
        });

        it('keeps the form filled when the announcement fails', async () => {
            axios.post = vi.fn().mockRejectedValue(new Error('boom'));

            const wrapper = await mountAdmin();
            await fillAnnouncement(wrapper);
            await buttonLabelled(wrapper, 'Envoyer').trigger('click');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain("Echec de l'envoi");
            expect(wrapper.findAll('input')[0].element.value).toBe('Nouvelle version');
        });

        it('clears the result message after a few seconds', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

            const wrapper = await mountAdmin();
            await fillAnnouncement(wrapper);
            await buttonLabelled(wrapper, 'Envoyer').trigger('click');
            await vi.advanceTimersByTimeAsync(5000);

            expect(wrapper.text()).not.toContain('Envoye avec succes');
        });

        it('sends the chosen channel and color', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

            const wrapper = await mountAdmin();
            await fillAnnouncement(wrapper);
            await wrapper.findAll('select').at(-1).setValue('discussion');
            await wrapper.find('button[title="Vert"]').trigger('click');
            await buttonLabelled(wrapper, 'Envoyer').trigger('click');
            await wrapper.vm.$nextTick();

            expect(axios.post.mock.calls[0][1]).toMatchObject({ channel: 'discussion', color: 3066993 });
        });

        it('sends only the fields that are filled in', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

            const wrapper = await mountAdmin();
            await fillAnnouncement(wrapper);
            await buttonLabelled(wrapper, '+ Ajouter un champ').trigger('click');
            await buttonLabelled(wrapper, '+ Ajouter un champ').trigger('click');

            const inputs = wrapper.findAll('input');
            await inputs[1].setValue('Score');
            await inputs[2].setValue('Recalculé');

            await buttonLabelled(wrapper, 'Envoyer').trigger('click');
            await wrapper.vm.$nextTick();

            expect(axios.post.mock.calls[0][1].fields).toEqual([{ name: 'Score', value: 'Recalculé', inline: false }]);
        });

        it('removes a field from the announcement', async () => {
            const wrapper = await mountAdmin();
            await buttonLabelled(wrapper, '+ Ajouter un champ').trigger('click');

            expect(buttonLabelled(wrapper, 'X')).toBeDefined();

            await buttonLabelled(wrapper, 'X').trigger('click');

            expect(buttonLabelled(wrapper, 'X')).toBeUndefined();
        });

        it('sends the footer when one is given', async () => {
            axios.post = vi.fn().mockResolvedValue({ data: { success: true } });

            const wrapper = await mountAdmin();
            await fillAnnouncement(wrapper);
            await wrapper.findAll('input').at(-1).setValue('WowPlanet');
            await buttonLabelled(wrapper, 'Envoyer').trigger('click');
            await wrapper.vm.$nextTick();

            expect(axios.post.mock.calls[0][1].footer).toBe('WowPlanet');
        });
    });
});
