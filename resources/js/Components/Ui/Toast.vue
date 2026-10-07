<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { t } = useI18n();
const { toasts, success, error, warning, dismiss } = useToast();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            success(flash.success);
        }
        if (flash?.error) {
            error(flash.error);
        }
        if (flash?.warning) {
            warning(flash.warning);
        }
    },
    { deep: true, immediate: true },
);

function toneClass(type) {
    if (type === 'error') return 'border-wz-danger/30 bg-wz-elevated text-wz-danger';
    if (type === 'warning') return 'border-wz-warning/40 bg-wz-accent-soft text-wz-fg';
    return 'border-wz-brand/30 bg-wz-elevated text-wz-fg';
}

function label(type) {
    if (type === 'error') return t('toast.error');
    if (type === 'warning') return t('toast.warning');
    return t('toast.success');
}
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-4">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="pointer-events-auto flex max-w-md items-center gap-3 rounded-xl border px-4 py-3 text-sm shadow-wz"
            :class="toneClass(toast.type)"
            role="status"
        >
            <span class="font-medium">{{ label(toast.type) }}:</span>
            <span class="text-wz-fg-muted">{{ toast.message }}</span>
            <button class="ms-auto text-wz-fg-muted hover:text-wz-fg" type="button" @click="dismiss(toast.id)">
                ×
            </button>
        </div>
    </div>
</template>
