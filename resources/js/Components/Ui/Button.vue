<script setup>
defineProps({
    type: { type: String, default: 'button' },
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'ghost', 'danger', 'accent'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    disabled: Boolean,
    block: Boolean,
});
</script>

<template>
    <button
        :type="type"
        class="wz-focus inline-flex items-center justify-center gap-2 font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
        :class="[
            block ? 'w-full' : '',
            size === 'sm' ? 'rounded-lg px-3 py-1.5 text-sm' : '',
            size === 'md' ? 'rounded-xl px-4 py-2.5 text-sm' : '',
            size === 'lg' ? 'rounded-xl px-5 py-3 text-base' : '',
            variant === 'primary' ? 'bg-wz-brand text-wz-brand-fg hover:opacity-90' : '',
            variant === 'secondary' ? 'border border-wz-border bg-wz-elevated text-wz-fg hover:bg-wz-muted' : '',
            variant === 'ghost' ? 'text-wz-fg-muted hover:bg-wz-muted hover:text-wz-fg' : '',
            variant === 'danger' ? 'bg-wz-danger text-white hover:opacity-90' : '',
            variant === 'accent' ? 'bg-wz-accent text-white hover:opacity-90' : '',
        ]"
        :disabled="disabled"
    >
        <slot />
    </button>
</template>
