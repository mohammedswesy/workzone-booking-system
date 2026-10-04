<script setup>
import AppShell from '@/Components/Ui/AppShell.vue';

defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <AppShell variant="admin" :title="title">
        <slot />
    </AppShell>
</template>
