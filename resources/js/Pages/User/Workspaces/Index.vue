<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  spaces: Object,   // paginate: { data, links, ... } أو array
  filters: Object,  // { search, per_page? }
})
</script>

<template>
  <AppLayout title="Spaces">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold">Available Workspaces</h2>

      <form method="get" class="flex gap-2">
        <input
          name="search"
          :value="filters?.search"
          placeholder="Search by name or location..."
          class="border rounded px-3 py-1.5 w-64"
        />
        <button class="bg-gray-900 text-white px-4 py-1.5 rounded">
          Search
        </button>
      </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
      <div
        v-for="s in (spaces.data ?? spaces)"
        :key="s.id"
        class="bg-white border rounded p-4 flex flex-col"
      >
        <img
          v-if="s.image_url"
          :src="s.image_url"
          alt=""
          class="w-full h-36 object-cover rounded mb-3"
        />

        <div class="flex items-start justify-between gap-2">
          <div>
            <h3 class="font-semibold">{{ s.name }}</h3>
            <p class="text-sm text-gray-600">{{ s.location }}</p>
          </div>

          <span
            v-if="s.offer_label || (s.active_discount_percent ?? 0) > 0"
            class="text-xs bg-rose-100 text-rose-700 px-2 py-0.5 rounded"
          >
            {{ s.offer_label ?? ('خصم ' + s.active_discount_percent + '%') }}
          </span>
        </div>

        <div class="mt-2 text-sm flex items-center gap-2">
          <template v-if="(s.active_discount_percent ?? 0) > 0 && s.effective_price_per_hour < s.price_per_hour">
            <span class="line-through text-gray-400">${{ Number(s.price_per_hour).toFixed(2) }}/h</span>
            <span class="font-semibold">${{ Number(s.effective_price_per_hour).toFixed(2) }}/h</span>
          </template>
          <template v-else>
            <span class="font-semibold">${{ Number(s.price_per_hour).toFixed(2) }}/h</span>
          </template>
        </div>

        <p class="text-xs text-gray-500">Capacity: {{ s.capacity }}</p>

        <div class="mt-3 flex gap-2">
          <Link
            :href="route('spaces.show', s.id)"
            class="inline-block border px-3 py-1.5 rounded text-sm hover:bg-gray-50"
          >
            عرض التفاصيل
          </Link>

          <Link
            :href="route('booking.create', { workspace_id: s.id })"
            class="inline-block bg-indigo-600 text-white px-3 py-1.5 rounded text-sm"
          >
            Book now
          </Link>
        </div>
      </div>
    </div>

    <Pagination v-if="spaces.links" :links="spaces.links" />
  </AppLayout>
</template>
