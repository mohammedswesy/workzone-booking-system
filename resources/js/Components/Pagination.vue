<script setup>
import UiPagination from '@/Components/Ui/Pagination.vue';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <UiPagination :links="links" />
</template>
