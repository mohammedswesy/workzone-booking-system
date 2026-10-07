import { describe, it, expect, vi } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import AppErrorBoundary from '@/Components/Ui/AppErrorBoundary.vue';

vi.mock('@/Components/Ui/Button.vue', () => ({
    default: {
        name: 'Button',
        setup(_, { slots }) {
            return () => h('button', slots.default?.());
        },
    },
}));

describe('AppErrorBoundary', () => {
    it('shows the friendly error UI for render errors', async () => {
        const Boom = defineComponent({
            name: 'Boom',
            setup() {
                throw new Error('boom-render');
            },
            render: () => h('div'),
        });

        const wrapper = mount(AppErrorBoundary, {
            slots: { default: () => h(Boom) },
        });
        await nextTick();

        expect(wrapper.text()).toContain('Something went wrong');
        expect(wrapper.text()).toContain('boom-render');
    });

    it('does not replace the page for unmount/cleanup errors', async () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const visible = ref(true);

        const ThrowOnUnmount = defineComponent({
            name: 'ThrowOnUnmount',
            unmounted() {
                throw new Error('cleanup-failed');
            },
            render: () => h('span', 'child'),
        });

        const Host = defineComponent({
            setup() {
                return () =>
                    h(AppErrorBoundary, null, {
                        default: () =>
                            visible.value ? h(ThrowOnUnmount) : h('div', 'still-here'),
                    });
            },
        });

        const wrapper = mount(Host);
        await nextTick();
        expect(wrapper.text()).toContain('child');

        visible.value = false;
        await nextTick();

        expect(wrapper.text()).toContain('still-here');
        expect(wrapper.text()).not.toContain('Something went wrong');
        expect(warn).toHaveBeenCalled();
        warn.mockRestore();
    });
});
