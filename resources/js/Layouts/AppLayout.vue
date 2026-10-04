<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppShell from '@/Components/Ui/AppShell.vue';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();

const variant = computed(() => {
    const raw = page.props.auth?.role;
    const role = typeof raw === 'string' ? raw.toLowerCase() : String(raw || 'guest').toLowerCase();
    if (role === 'admin') return 'admin';
    if (role === 'owner') return 'owner';
    if (role === 'user') return 'user';
    return 'app';
});
</script>

<template>
    <AppShell :variant="variant" :title="title">
        <slot />
    </AppShell>
</template>
