import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/', props: {} });

    return {
        __page: page,
        Head: { name: 'Head', render: () => null },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
        usePage: () => page,
        router: { visit: vi.fn(), on: vi.fn(() => () => {}) },
    };
});

import { __page } from '@inertiajs/vue3';
import { useCharacterStore } from '../../stores/character';
import { useTaskStore } from '../../stores/tasks';
import { mountWithPlugins } from '../../tests/helpers';
import TaskSidebarInertia from './TaskSidebarInertia.vue';

const makeTask = (overrides = {}) => ({
    id: 1,
    realm_slug: 'hyjal',
    character_name: 'arthas',
    name: 'Coffre hebdomadaire',
    reset_type: 'weekly',
    is_completed: false,
    ...overrides,
});

const mountSidebar = (options = {}) => mountWithPlugins(TaskSidebarInertia, {
    initialState: {
        tasks: { sidebarOpen: true, tasks: [makeTask()], ...(options.tasks || {}) },
        character: { isAuthenticated: true, ...(options.character || {}) },
    },
    stubActions: options.stubActions,
});

const sections = wrapper => wrapper.findAll('[data-testid="character-section"]');
const expandFirst = async wrapper => wrapper.findAll('[data-testid="character-header"]')[0].trigger('click');

beforeEach(() => {
    __page.url = '/';
});

describe('TaskSidebarInertia', () => {
    it('keeps the panel closed until the toggle is used', async () => {
        const wrapper = await mountSidebar({ tasks: { sidebarOpen: false } });

        expect(wrapper.find('[data-testid="sidebar-panel"]').exists()).toBe(false);

        const taskStore = useTaskStore(wrapper.vm.$pinia);
        await wrapper.find('[data-testid="sidebar-toggle"]').trigger('click');

        expect(taskStore.toggleSidebar).toHaveBeenCalled();
    });

    it('shows the number of pending tasks on the toggle', async () => {
        const wrapper = await mountSidebar({
            tasks: { tasks: [makeTask(), makeTask({ id: 2 }), makeTask({ id: 3, is_completed: true })] },
        });

        expect(wrapper.find('[data-testid="pending-badge"]').text()).toBe('2');
    });

    it('hides the badge when nothing is pending', async () => {
        const wrapper = await mountSidebar({ tasks: { tasks: [makeTask({ is_completed: true })] } });

        expect(wrapper.find('[data-testid="pending-badge"]').exists()).toBe(false);
    });

    it('fetches the characters of an authenticated visitor on mount', async () => {
        const wrapper = await mountSidebar();
        const store = useCharacterStore(wrapper.vm.$pinia);

        expect(store.fetchUserCharacters).toHaveBeenCalled();
    });

    it('fetches nothing for an anonymous visitor', async () => {
        const wrapper = await mountSidebar({ character: { isAuthenticated: false } });
        const store = useCharacterStore(wrapper.vm.$pinia);

        expect(store.fetchUserCharacters).not.toHaveBeenCalled();
    });

    it('fetches nothing when the characters are already loaded', async () => {
        const wrapper = await mountSidebar({
            character: { userCharacters: [{ name: 'Arthas', realmSlug: 'hyjal' }] },
        });
        const store = useCharacterStore(wrapper.vm.$pinia);

        expect(store.fetchUserCharacters).not.toHaveBeenCalled();
    });

    it('invites the visitor to open a character sheet when there is nothing to show', async () => {
        const wrapper = await mountSidebar({ tasks: { tasks: [] } });

        expect(wrapper.text()).toContain("Ouvrez la fiche d'un personnage");
        expect(sections(wrapper)).toHaveLength(0);
    });

    it('lists one section per character holding tasks', async () => {
        const wrapper = await mountSidebar({
            tasks: { tasks: [makeTask(), makeTask({ id: 2 }), makeTask({ id: 3, character_name: 'jaina' })] },
        });

        expect(sections(wrapper)).toHaveLength(2);
    });

    it('adds the character of the current page to the list', async () => {
        __page.url = '/character/kazzak/thrall';

        const wrapper = await mountSidebar();

        expect(sections(wrapper)).toHaveLength(2);
        expect(wrapper.text()).toContain('Thrall');
    });

    it('puts the character of the current page first', async () => {
        __page.url = '/character/kazzak/thrall';

        const wrapper = await mountSidebar();

        expect(sections(wrapper)[0].text()).toContain('Thrall');
    });

    it('does not duplicate the character of the current page', async () => {
        __page.url = '/character/hyjal/Arthas';

        const wrapper = await mountSidebar();

        expect(sections(wrapper)).toHaveLength(1);
    });

    it('decodes the character of the current page', async () => {
        __page.url = '/character/conseil-des-ombres/%C3%89lune';

        const wrapper = await mountSidebar();

        expect(wrapper.text()).toContain('Élune');
        expect(wrapper.text()).toContain('conseil-des-ombres');
    });

    it('ignores a page that is not a character sheet', async () => {
        __page.url = '/my-characters';

        const wrapper = await mountSidebar();

        expect(sections(wrapper)).toHaveLength(1);
    });

    it('prefers the character details known by the store', async () => {
        const wrapper = await mountSidebar({
            character: {
                userCharacters: [{
                    name: 'Arthas',
                    realmSlug: 'hyjal',
                    realm: { name: 'Hyjal' },
                    avatarUrl: 'https://render.worldofwarcraft.com/arthas.jpg',
                }],
            },
        });

        expect(wrapper.find('img').attributes('src')).toBe('https://render.worldofwarcraft.com/arthas.jpg');
        expect(wrapper.text()).toContain('Hyjal');
    });

    it('falls back to the initial of the character without an avatar', async () => {
        const wrapper = await mountSidebar();

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('A');
    });

    it('hides the tasks of a collapsed character', async () => {
        const wrapper = await mountSidebar();

        expect(wrapper.findAll('[data-testid="task-item"]')).toHaveLength(0);
    });

    it('shows the tasks of an expanded character', async () => {
        const wrapper = await mountSidebar();

        await expandFirst(wrapper);

        expect(wrapper.findAll('[data-testid="task-item"]')).toHaveLength(1);
        expect(wrapper.text()).toContain('Coffre hebdomadaire');
    });

    it('collapses a character back', async () => {
        const wrapper = await mountSidebar();

        await expandFirst(wrapper);
        await expandFirst(wrapper);

        expect(wrapper.findAll('[data-testid="task-item"]')).toHaveLength(0);
    });

    it('expands each character independently', async () => {
        const wrapper = await mountSidebar({
            tasks: { tasks: [makeTask(), makeTask({ id: 2, character_name: 'jaina' })] },
        });

        await expandFirst(wrapper);

        expect(wrapper.findAll('[data-testid="task-item"]')).toHaveLength(1);
    });

    it('toggles and deletes a task through the store', async () => {
        const wrapper = await mountSidebar();
        const taskStore = useTaskStore(wrapper.vm.$pinia);

        await expandFirst(wrapper);
        await wrapper.find('[data-testid="task-checkbox"]').trigger('click');
        await wrapper.find('[data-testid="delete-task-btn"]').trigger('click');

        expect(taskStore.toggleTask).toHaveBeenCalledWith(1);
        expect(taskStore.deleteTask).toHaveBeenCalledWith(1);
    });

    it('marks the reset period of each task', async () => {
        const wrapper = await mountSidebar({
            tasks: {
                tasks: [
                    makeTask({ id: 1, reset_type: 'daily' }),
                    makeTask({ id: 2, reset_type: 'weekly' }),
                    makeTask({ id: 3, reset_type: 'monthly' }),
                ],
            },
        });

        await expandFirst(wrapper);

        const badges = wrapper.findAll('[data-testid="task-item"]').map(t => t.findAll('span').at(-1).text());

        expect(badges).toEqual(['J', 'H', 'M']);
    });

    it('opens the creation form on demand', async () => {
        const wrapper = await mountSidebar();

        await expandFirst(wrapper);
        expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(false);

        await wrapper.find('[data-testid="add-task-btn"]').trigger('click');

        expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(true);
    });

    it('creates a task from the form and closes it', async () => {
        const wrapper = await mountSidebar();
        const taskStore = useTaskStore(wrapper.vm.$pinia);

        await expandFirst(wrapper);
        await wrapper.find('[data-testid="add-task-btn"]').trigger('click');
        await wrapper.find('[data-testid="task-name-input"]').setValue('  Donjon mythique  ');
        await wrapper.find('[data-testid="task-reset-select"]').setValue('monthly');
        await wrapper.find('[data-testid="task-form"]').trigger('submit');

        expect(taskStore.createTask).toHaveBeenCalledWith('hyjal', 'arthas', 'Donjon mythique', 'monthly');
        expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(false);
    });

    it('refuses to create a task without a name', async () => {
        const wrapper = await mountSidebar();
        const taskStore = useTaskStore(wrapper.vm.$pinia);

        await expandFirst(wrapper);
        await wrapper.find('[data-testid="add-task-btn"]').trigger('click');
        await wrapper.find('[data-testid="task-name-input"]').setValue('   ');
        await wrapper.find('[data-testid="task-form"]').trigger('submit');

        expect(taskStore.createTask).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(true);
    });

    it('closes the form without creating anything', async () => {
        const wrapper = await mountSidebar();
        const taskStore = useTaskStore(wrapper.vm.$pinia);

        await expandFirst(wrapper);
        await wrapper.find('[data-testid="add-task-btn"]').trigger('click');
        await wrapper.findAll('[data-testid="task-form"] button').at(-1).trigger('click');

        expect(taskStore.createTask).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(false);
    });

    it('closes the panel from the header and from the overlay', async () => {
        const wrapper = await mountSidebar();
        const taskStore = useTaskStore(wrapper.vm.$pinia);

        await wrapper.find('.fixed.inset-0').trigger('click');
        await wrapper.findAll('[data-testid="sidebar-panel"] button')[0].trigger('click');

        expect(taskStore.toggleSidebar).toHaveBeenCalledTimes(2);
    });
});
