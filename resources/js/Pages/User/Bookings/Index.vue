<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link } from '@inertiajs/vue3'


const props = defineProps({
  bookings: Object // Laravel paginate: { data, links, ... }
})
</script>

<template>
  <AppLayout title="My Bookings">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold">My Bookings</h2>
      <Link :href="route('booking.create')" class="bg-indigo-600 text-white px-3 py-1.5 rounded">
        New Booking
      </Link>
    </div>

    <div class="bg-white border rounded">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">#</th>
            <th class="px-3 py-2 text-left">Workspace</th>
            <th class="px-3 py-2 text-left">Hours</th>
            <th class="px-3 py-2 text-left">Total</th>
            <th class="px-3 py-2 text-left">Status</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in bookings.data" :key="b.id" class="border-t">
            <td class="px-3 py-2">{{ b.id }}</td>
            <td class="px-3 py-2">{{ b.workspace?.name }}</td>
            <td class="px-3 py-2">{{ b.hours }}</td>
            <td class="px-3 py-2">{{ b.total_price }}</td>
            <td class="px-3 py-2">
              <span class="px-2 py-0.5 rounded text-xs border"
                    :class="{
                      'bg-yellow-50 border-yellow-300 text-yellow-700': b.status==='pending',
                      'bg-green-50 border-green-300 text-green-700': b.status==='paid',
                      'bg-red-50 border-red-300 text-red-700': b.status==='cancelled'
                    }">
                {{ b.status }}
              </span>
            </td>
            <td class="px-3 py-2 text-right">
              <Link :href="route('user.bookings.edit', b.id)" class="text-indigo-600 hover:underline">Edit</Link>
            </td>
          </tr>
          <tr v-if="!bookings.data?.length">
            <td class="px-3 py-6 text-center text-gray-500" colspan="6">No bookings yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination :links="bookings.links" />
  </AppLayout>
</template>
