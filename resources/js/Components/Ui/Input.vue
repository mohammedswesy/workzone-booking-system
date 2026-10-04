<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    id: String,
    placeholder: String,
    disabled: Boolean,
    error: String,
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="grid gap-1.5">
        <label v-if="$slots.label || $attrs.label" :for="id" class="text-sm font-medium text-wz-fg">
            <slot name="label" />
        </label>
        <input
            :id="id"
            :type="type"
            class="wz-focus w-full rounded-xl border border-wz-border bg-wz-elevated px-3 py-2.5 text-sm text-wz-fg placeholder:text-wz-fg-muted"
            :class="error ? 'border-wz-danger' : ''"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <p v-if="error" class="text-xs text-wz-danger">{{ error }}</p>
    </div>
</template>
