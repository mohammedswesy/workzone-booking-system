<script setup>
defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    id: String,
    disabled: Boolean,
    error: String,
    options: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="grid gap-1.5">
        <label v-if="$slots.label" :for="id" class="text-sm font-medium text-wz-fg">
            <slot name="label" />
        </label>
        <select
            :id="id"
            class="wz-focus w-full rounded-xl border border-wz-border bg-wz-elevated px-3 py-2.5 text-sm text-wz-fg disabled:cursor-not-allowed disabled:opacity-55 disabled:text-wz-fg-muted"
            :class="error ? 'border-wz-danger' : ''"
            :value="modelValue"
            :disabled="disabled"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <slot>
                <option v-for="opt in options" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </slot>
        </select>
        <p v-if="error" class="text-xs text-wz-danger">{{ error }}</p>
    </div>
</template>
