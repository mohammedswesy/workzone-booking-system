<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  bookings: Object
})

const form = useForm({ status: '' })

function updateStatus(id, status) {
  form.status = status
  form.put(route('admin.bookings.update', id), { preserveScroll: true })
}
</script>

<template>
  <AppLayout title="All Bookings">
    <div class="bg-white border rounded">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">#</th>
            <th class="px-3 py-2 text-left">Workspace</th>
            <th class="px-3 py-2 text-left">User</th>
            <th class="px-3 py-2 text-left">Hours</th>
            <th class="px-3 py-2 text-left">Total</th>
            <th class="px-3 py-2 text-left">Status</th>
            <th class="px-3 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in bookings.data" :key="b.id" class="border-t">
            <td class="px-3 py-2">{{ b.id }}</td>
            <td class="px-3 py-2">{{ b.workspace?.name }}</td>
            <td class="px-3 py-2">{{ b.user?.name }}</td>
            <td class="px-3 py-2">{{ b.hours }}</td>
            <td class="px-3 py-2">{{ b.total_price }}</td>
            <td class="px-3 py-2">{{ b.status }}</td>
            <td class="px-3 py-2 text-right space-x-2">
              <button @click="updateStatus(b.id, 'paid')" class="px-2 py-1 text-xs rounded bg-green-600 text-white">Mark Paid</button>
              <button @click="updateStatus(b.id, 'cancelled')" class="px-2 py-1 text-xs rounded bg-red-600 text-white">Cancel</button>
            </td>
          </tr>
          <tr v-if="!bookings.data?.length">
            <td colspan="7" class="px-3 py-6 text-center text-gray-500">No bookings.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination :links="bookings.links" />
  </AppLayout>
</template>
