import { describe, it, expect, vi } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import TaskSidebar from './TaskSidebar.vue';
import { useTaskStore } from '../stores/tasks';
import { useCharacterStore } from '../stores/character';

describe('TaskSidebar', () => {
    // ─── Toggle button ──────────────────────────────────

    it('renders the toggle button', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: { tasks: [], sidebarOpen: false },
                character: { userCharacters: [] },
            },
        });

        expect(wrapper.find('[data-testid="sidebar-toggle"]').exists()).toBe(true);
    });

    it('shows pending count badge when tasks exist', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: {
                    tasks: [
                        { id: 1, realm_slug: 'hyjal', character_name: 'thrall', name: 'A', is_completed: false, reset_type: 'daily' },
                        { id: 2, realm_slug: 'hyjal', character_name: 'thrall', name: 'B', is_completed: true, reset_type: 'weekly' },
                    ],
                    sidebarOpen: false,
                },
                character: { userCharacters: [] },
            },
        });

        const badge = wrapper.find('[data-testid="pending-badge"]');
        expect(badge.exists()).toBe(true);
        expect(badge.text()).toBe('1');
    });

    it('does not show badge when no pending tasks', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: { tasks: [], sidebarOpen: false },
                character: { userCharacters: [] },
            },
        });

        expect(wrapper.find('[data-testid="pending-badge"]').exists()).toBe(false);
    });

    // ─── Sidebar panel ──────────────────────────────────

    it('sidebar panel is hidden when sidebarOpen is false', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: { tasks: [], sidebarOpen: false },
                character: { userCharacters: [] },
            },
        });

        expect(wrapper.find('[data-testid="sidebar-panel"]').exists()).toBe(false);
    });

    it('sidebar panel is visible when sidebarOpen is true', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: { tasks: [], sidebarOpen: true },
                character: { userCharacters: [] },
            },
        });

        expect(wrapper.find('[data-testid="sidebar-panel"]').exists()).toBe(true);
    });

    it('toggles sidebar on button click', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: { tasks: [], sidebarOpen: false },
                character: { userCharacters: [] },
            },
        });

        const store = useTaskStore();
        await wrapper.find('[data-testid="sidebar-toggle"]').trigger('click');
        expect(store.toggleSidebar).toHaveBeenCalled();
    });

    // ─── Character list ─────────────────────────────────

    it('shows empty message when no tasks and not on character page', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: { tasks: [], sidebarOpen: true },
                character: { userCharacters: [] },
            },
        });

        expect(wrapper.text()).toContain('Ouvrez la fiche');
        expect(wrapper.findAll('[data-testid="character-section"]')).toHaveLength(0);
    });

    it('shows current character on character page even without tasks', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialRoute: '/character/hyjal/thrall',
            initialState: {
                tasks: { tasks: [], sidebarOpen: true },
                character: {
                    userCharacters: [
                        { name: 'Thrall', realm: { slug: 'hyjal', name: 'Hyjal' }, className: 'Chaman', avatarUrl: '/avatar.jpg' },
                    ],
                },
            },
        });

        const characters = wrapper.findAll('[data-testid="character-section"]');
        expect(characters).toHaveLength(1);
    });

    it('displays only characters that have tasks', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: {
                    tasks: [
                        { id: 1, realm_slug: 'hyjal', character_name: 'thrall', name: 'Task A', is_completed: false, reset_type: 'daily' },
                    ],
                    sidebarOpen: true,
                },
                character: {
                    userCharacters: [
                        { name: 'Thrall', realm: { slug: 'hyjal', name: 'Hyjal' }, className: 'Chaman', avatarUrl: '/avatar1.jpg' },
                        { name: 'Jaina', realm: { slug: 'dalaran', name: 'Dalaran' }, className: 'Mage', avatarUrl: '/avatar2.jpg' },
                    ],
                },
            },
        });

        const characters = wrapper.findAll('[data-testid="character-section"]');
        expect(characters).toHaveLength(1);
    });

    // ─── Task items ─────────────────────────────────────

    it('displays tasks with reset type badges', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: {
                    tasks: [
                        { id: 1, realm_slug: 'hyjal', character_name: 'thrall', name: 'Donjon', is_completed: false, reset_type: 'daily' },
                        { id: 2, realm_slug: 'hyjal', character_name: 'thrall', name: 'Raid', is_completed: true, reset_type: 'weekly' },
                    ],
                    sidebarOpen: true,
                },
                character: {
                    userCharacters: [
                        { name: 'Thrall', realm: { slug: 'hyjal', name: 'Hyjal' }, className: 'Chaman', avatarUrl: '/avatar.jpg' },
                    ],
                },
            },
        });

        // Click to expand character section
        const section = wrapper.find('[data-testid="character-section"]');
        await section.find('[data-testid="character-header"]').trigger('click');

        const tasks = wrapper.findAll('[data-testid="task-item"]');
        expect(tasks).toHaveLength(2);

        expect(wrapper.text()).toContain('Donjon');
        expect(wrapper.text()).toContain('Raid');
    });

    it('displays M badge for monthly tasks', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: {
                    tasks: [
                        { id: 1, realm_slug: 'hyjal', character_name: 'thrall', name: 'Objectif mensuel', is_completed: false, reset_type: 'monthly' },
                    ],
                    sidebarOpen: true,
                },
                character: {
                    userCharacters: [
                        { name: 'Thrall', realm: { slug: 'hyjal', name: 'Hyjal' }, className: 'Chaman', avatarUrl: '/avatar.jpg' },
                    ],
                },
            },
        });

        await wrapper.find('[data-testid="character-header"]').trigger('click');

        const tasks = wrapper.findAll('[data-testid="task-item"]');
        expect(tasks).toHaveLength(1);
        expect(wrapper.text()).toContain('M');
    });

    // ─── Create task form ───────────────────────────────

    it('shows create form when add button is clicked', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: {
                    tasks: [
                        { id: 1, realm_slug: 'hyjal', character_name: 'thrall', name: 'Task', is_completed: false, reset_type: 'daily' },
                    ],
                    sidebarOpen: true,
                },
                character: {
                    userCharacters: [
                        { name: 'Thrall', realm: { slug: 'hyjal', name: 'Hyjal' }, className: 'Chaman', avatarUrl: '/avatar.jpg' },
                    ],
                },
            },
        });

        // Expand character
        await wrapper.find('[data-testid="character-header"]').trigger('click');

        // Click add button
        await wrapper.find('[data-testid="add-task-btn"]').trigger('click');

        expect(wrapper.find('[data-testid="task-form"]').exists()).toBe(true);
    });

    it('calls createTask on form submit', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: {
                    tasks: [
                        { id: 1, realm_slug: 'hyjal', character_name: 'thrall', name: 'Task', is_completed: false, reset_type: 'daily' },
                    ],
                    sidebarOpen: true,
                },
                character: {
                    userCharacters: [
                        { name: 'Thrall', realm: { slug: 'hyjal', name: 'Hyjal' }, className: 'Chaman', avatarUrl: '/avatar.jpg' },
                    ],
                },
            },
        });

        const taskStore = useTaskStore();

        // Expand character + open form
        await wrapper.find('[data-testid="character-header"]').trigger('click');
        await wrapper.find('[data-testid="add-task-btn"]').trigger('click');

        // Fill form
        await wrapper.find('[data-testid="task-name-input"]').setValue('Nouveau donjon');
        await wrapper.find('[data-testid="task-reset-select"]').setValue('weekly');
        await wrapper.find('[data-testid="task-form"]').trigger('submit');

        expect(taskStore.createTask).toHaveBeenCalledWith('hyjal', 'thrall', 'Nouveau donjon', 'weekly');
    });

    // ─── Toggle and delete ──────────────────────────────

    it('calls toggleTask when checkbox is clicked', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: {
                    tasks: [
                        { id: 42, realm_slug: 'hyjal', character_name: 'thrall', name: 'Task', is_completed: false, reset_type: 'daily' },
                    ],
                    sidebarOpen: true,
                },
                character: {
                    userCharacters: [
                        { name: 'Thrall', realm: { slug: 'hyjal', name: 'Hyjal' }, className: 'Chaman', avatarUrl: '/avatar.jpg' },
                    ],
                },
            },
        });

        const taskStore = useTaskStore();

        // Expand character
        await wrapper.find('[data-testid="character-header"]').trigger('click');

        // Click checkbox
        await wrapper.find('[data-testid="task-checkbox"]').trigger('click');
        expect(taskStore.toggleTask).toHaveBeenCalledWith(42);
    });

    it('calls deleteTask when delete button is clicked', async () => {
        const wrapper = await mountWithPlugins(TaskSidebar, {
            initialState: {
                tasks: {
                    tasks: [
                        { id: 42, realm_slug: 'hyjal', character_name: 'thrall', name: 'Task', is_completed: false, reset_type: 'daily' },
                    ],
                    sidebarOpen: true,
                },
                character: {
                    userCharacters: [
                        { name: 'Thrall', realm: { slug: 'hyjal', name: 'Hyjal' }, className: 'Chaman', avatarUrl: '/avatar.jpg' },
                    ],
                },
            },
        });

        const taskStore = useTaskStore();

        // Expand character
        await wrapper.find('[data-testid="character-header"]').trigger('click');

        // Click delete
        await wrapper.find('[data-testid="delete-task-btn"]').trigger('click');
        expect(taskStore.deleteTask).toHaveBeenCalledWith(42);
    });
});
