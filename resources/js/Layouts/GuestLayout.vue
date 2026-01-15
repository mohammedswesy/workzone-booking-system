<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <!-- تم حذف الشعار -->
        <!-- تقدر تضيف اسم WorkZone بدل الصورة -->
        <div class="mb-4 text-2xl font-bold text-indigo-600">
            WorkZone
        </div>

        <div
            class="mt-2 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
