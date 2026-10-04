<script setup>
defineProps({
    label: { type: String, default: '' },
});
</script>

<template>
    <div class="flex items-center justify-center gap-3 py-10 text-sm text-wz-fg-muted" role="status">
        <span class="h-5 w-5 animate-spin rounded-full border-2 border-wz-border border-t-wz-brand" />
        <span>{{ label || $t('common.loading') }}</span>
    </div>
</template>
