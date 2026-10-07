import { reactive } from 'vue';

const state = reactive({
    items: [],
});

let seed = 1;

export function useToast() {
    function push(message, type = 'success', timeout = 3200) {
        const id = seed++;
        state.items.push({ id, message, type });

        window.setTimeout(() => dismiss(id), timeout);
    }

    function dismiss(id) {
        const index = state.items.findIndex((item) => item.id === id);
        if (index >= 0) {
            state.items.splice(index, 1);
        }
    }

    return {
        toasts: state.items,
        success: (message) => push(message, 'success'),
        error: (message) => push(message, 'error'),
        warning: (message) => push(message, 'warning', 6000),
        dismiss,
    };
}
