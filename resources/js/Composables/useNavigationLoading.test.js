import { describe, it, expect, vi, beforeEach } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { useNavigationLoading } from '@/Composables/useNavigationLoading';

const unsubscribers = [];

vi.mock('@inertiajs/vue3', () => {
    const router = {
        on: vi.fn((event, handler) => {
            const off = vi.fn();
            unsubscribers.push({ event, handler, off });
            return off;
        }),
        off: undefined,
        get: vi.fn(),
        put: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        visit: vi.fn(),
    };

    return {
        router,
        Head: { name: 'Head', render: () => null },
        Link: {
            name: 'Link',
            props: ['href'],
            render() {
                return h('a', { href: this.href }, this.$slots.default?.());
            },
        },
        usePage: () => ({
            props: {
                flash: {},
                auth: { user: { id: 1, role: 'admin' } },
            },
        }),
    };
});

function mountWithComposable() {
    let api;
    const Host = defineComponent({
        setup() {
            api = useNavigationLoading();
            return () => h('div', { 'data-loading': api.loading.value ? '1' : '0' });
        },
    });
    const wrapper = mount(Host);
    return { wrapper, api };
}

beforeEach(() => {
    unsubscribers.length = 0;
    vi.clearAllMocks();
});

describe('useNavigationLoading', () => {
    it('subscribes to start/finish/error and unsubscribes on unmount', async () => {
        const { wrapper, api } = mountWithComposable();

        expect(unsubscribers.map((u) => u.event)).toEqual(['start', 'finish', 'error']);
        expect(api.loading.value).toBe(false);

        unsubscribers[0].handler();
        await nextTick();
        expect(api.loading.value).toBe(true);

        unsubscribers[1].handler();
        await nextTick();
        expect(api.loading.value).toBe(false);

        wrapper.unmount();
        for (const entry of unsubscribers) {
            expect(entry.off).toHaveBeenCalledTimes(1);
        }
    });
});

const layoutStubs = {
    AppLayout: { template: '<div><slot /></div>' },
    PageHeader: true,
    Pagination: true,
    EmptyState: true,
    LoadingState: true,
    Button: true,
    Badge: true,
    Input: true,
    Select: true,
    ConfirmDialog: true,
    WorkspaceCard: true,
    Head: true,
    Link: true,
};

function paginator(extra = {}) {
    return {
        data: [],
        links: [
            { url: null, label: '&laquo; Previous', active: false },
            { url: '/?page=1', label: '1', active: true },
            { url: null, label: 'Next &raquo;', active: false },
        ],
        ...extra,
    };
}

describe('index pages mount and unmount without router.off', () => {
    it('Admin/Users/Index', async () => {
        const AdminUsersIndex = (await import('@/Pages/Admin/Users/Index.vue')).default;
        const wrapper = mount(AdminUsersIndex, {
            props: {
                users: paginator(),
                filters: { role: '', search: '' },
            },
            global: { stubs: layoutStubs },
        });
        await flushPromises();
        expect(() => wrapper.unmount()).not.toThrow();
        for (const entry of unsubscribers) {
            expect(entry.off).toHaveBeenCalled();
        }
        expect(unsubscribers.some((u) => u.event === 'start')).toBe(true);
    });

    it('Owner/Bookings/Index', async () => {
        unsubscribers.length = 0;
        const OwnerBookingsIndex = (await import('@/Pages/Owner/Bookings/Index.vue')).default;
        const wrapper = mount(OwnerBookingsIndex, {
            props: {
                bookings: paginator(),
                filters: { status: '', search: '', per_page: 12 },
            },
            global: { stubs: layoutStubs },
        });
        await flushPromises();
        expect(() => wrapper.unmount()).not.toThrow();
        for (const entry of unsubscribers) {
            expect(entry.off).toHaveBeenCalled();
        }
    });

    it('User/Workspaces/Index', async () => {
        unsubscribers.length = 0;
        const SpacesIndex = (await import('@/Pages/User/Workspaces/Index.vue')).default;
        const wrapper = mount(SpacesIndex, {
            props: {
                spaces: paginator(),
                filters: {},
                locations: [],
                amenities: [],
            },
            global: { stubs: layoutStubs },
        });
        await flushPromises();
        expect(() => wrapper.unmount()).not.toThrow();
        for (const entry of unsubscribers) {
            expect(entry.off).toHaveBeenCalled();
        }
    });
});
