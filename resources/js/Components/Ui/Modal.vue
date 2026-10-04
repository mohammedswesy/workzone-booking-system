<script setup>
import { onMounted, onUnmounted, watch } from 'vue';
import Button from './Button.vue';

const props = defineProps({
    show: Boolean,
    title: { type: String, default: '' },
    closeable: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

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
    (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
    },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            @click.self="close"
        >
            <div class="wz-surface w-full max-w-lg overflow-hidden">
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
    </Teleport>
</template>
