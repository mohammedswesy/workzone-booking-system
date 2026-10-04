<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { t } = useI18n();
const { toasts, success, error, dismiss } = useToast();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            success(flash.success);
        }
        if (flash?.error) {
            error(flash.error);
        }
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-4">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="pointer-events-auto flex max-w-md items-center gap-3 rounded-xl border px-4 py-3 text-sm shadow-wz"
            :class="
                toast.type === 'error'
                    ? 'border-wz-danger/30 bg-wz-elevated text-wz-danger'
                    : 'border-wz-brand/30 bg-wz-elevated text-wz-fg'
            "
            role="status"
        >
            <span class="font-medium">
                {{ toast.type === 'error' ? t('toast.error') : t('toast.success') }}:
            </span>
            <span class="text-wz-fg-muted">{{ toast.message }}</span>
            <button class="ms-auto text-wz-fg-muted hover:text-wz-fg" type="button" @click="dismiss(toast.id)">
                ×
            </button>
        </div>
    </div>
</template>
