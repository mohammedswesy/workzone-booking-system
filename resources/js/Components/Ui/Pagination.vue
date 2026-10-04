<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center gap-1" aria-label="Pagination">
        <template v-for="(link, index) in links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                class="rounded-lg px-3 py-1.5 text-sm transition"
                :class="link.active ? 'bg-wz-brand text-wz-brand-fg' : 'border border-wz-border text-wz-fg-muted hover:bg-wz-muted'"
                v-html="link.label"
            />
            <span
                v-else
                class="rounded-lg px-3 py-1.5 text-sm text-wz-fg-muted opacity-50"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
