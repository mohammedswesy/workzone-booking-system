<script setup>
defineProps({
    tone: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'brand', 'accent', 'success', 'warning', 'danger'].includes(v),
    },
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="{
            'bg-wz-muted text-wz-fg': tone === 'neutral',
            'bg-wz-brand-soft text-wz-brand': tone === 'brand',
            'bg-wz-accent-soft text-wz-accent': tone === 'accent',
            'bg-emerald-100 text-wz-success dark:bg-emerald-950/40': tone === 'success',
            'bg-amber-100 text-wz-warning dark:bg-amber-950/40': tone === 'warning',
            'bg-red-100 text-wz-danger dark:bg-red-950/40': tone === 'danger',
        }"
    >
        <slot />
    </span>
</template>
