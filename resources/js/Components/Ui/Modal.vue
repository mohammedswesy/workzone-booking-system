<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import Button from './Button.vue';

const props = defineProps({
    show: Boolean,
    title: { type: String, default: '' },
    closeable: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const panelRef = ref(null);
let previousFocus = null;

function close() {
    if (props.closeable) {
        emit('close');
    }
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        close();
    }
}

watch(
    () => props.show,
    async (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            previousFocus = document.activeElement;
            await nextTick();
            panelRef.value?.focus();
        } else if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
            previousFocus = null;
        }
    },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
    if (previousFocus && typeof previousFocus.focus === 'function') {
        previousFocus.focus();
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            :aria-label="title || undefined"
            @click.self="close"
        >
            <div
                ref="panelRef"
                tabindex="-1"
                class="wz-focus w-full max-w-lg overflow-hidden outline-none"
                @keydown.stop
            >
                <div class="wz-surface w-full overflow-hidden">
                    <div class="flex items-center justify-between border-b border-wz-border px-4 py-3">
                        <h2 class="text-base font-semibold text-wz-fg">
                            <slot name="title">{{ title }}</slot>
                        </h2>
                        <Button v-if="closeable" variant="ghost" size="sm" @click="close">
                            {{ $t('common.close') }}
                        </Button>
                    </div>
                    <div class="px-4 py-4">
                        <slot />
                    </div>
                    <div v-if="$slots.footer" class="border-t border-wz-border px-4 py-3">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
