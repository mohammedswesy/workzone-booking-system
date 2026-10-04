<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="wz-surface flex flex-col items-center justify-center px-6 py-12 text-center">
        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-wz-brand-soft text-wz-brand">
            <slot name="icon">∅</slot>
        </div>
        <h3 class="text-base font-semibold text-wz-fg">
            <slot name="title">{{ title }}</slot>
        </h3>
        <p class="mt-1 max-w-md text-sm text-wz-fg-muted">
            <slot name="description">{{ description }}</slot>
        </p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
