<script setup>
import Button from './Button.vue';
import Modal from './Modal.vue';

defineProps({
    show: Boolean,
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: '' },
    cancelLabel: { type: String, default: '' },
    danger: Boolean,
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal :show="show" :title="title" @close="emit('cancel')">
        <p class="text-sm text-wz-fg-muted">
            <slot>{{ message }}</slot>
        </p>
        <template #footer>
            <div class="flex items-center justify-end gap-2">
                <Button variant="secondary" @click="emit('cancel')">
                    {{ cancelLabel || $t('common.cancel') }}
                </Button>
                <Button :variant="danger ? 'danger' : 'primary'" @click="emit('confirm')">
                    {{ confirmLabel || $t('common.confirm') }}
                </Button>
            </div>
        </template>
    </Modal>
</template>
