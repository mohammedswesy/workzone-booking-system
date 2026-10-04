<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div class="wz-surface p-5">
        <div class="text-sm text-wz-fg-muted">{{ label }}</div>
        <div class="mt-2 font-display text-3xl font-semibold tracking-tight text-wz-fg">
            {{ value }}
        </div>
        <div v-if="hint || $slots.hint" class="mt-2 text-xs text-wz-fg-muted">
            <slot name="hint">{{ hint }}</slot>
        </div>
    </div>
</template>
