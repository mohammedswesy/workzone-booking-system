<script setup>
import { Link } from '@inertiajs/vue3'
defineProps({ space: Object })
</script>

<template>
  <div class="border rounded-lg p-4 flex items-center gap-4 bg-white">
    <img :src="space.image_url" :alt="space.name" class="w-24 h-24 object-cover rounded-md" />
    <div class="flex-1">
      <h3 class="font-semibold">{{ space.name }}</h3>
      <p class="text-sm text-slate-600">{{ space.location }}</p>
      <p class="text-sm text-slate-600">Capacity: {{ space.capacity }}</p>
    </div>
    <div class="text-right">
      <div class="font-semibold">${{ space.price_per_hour }}/h</div>
      <Link :href="`/booking/${space.id}`" class="mt-2 inline-block bg-blue-600 text-white px-4 py-2 rounded-md">
        Book Now
      </Link>
    </div>
  </div>
</template>
