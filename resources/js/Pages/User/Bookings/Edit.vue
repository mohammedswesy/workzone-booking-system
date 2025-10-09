<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link } from '@inertiajs/vue3'


const props = defineProps({
  booking: Object,
  workspaces: Array
})

const form = useForm({
  workspace_id: props.booking.workspace_id,
  hours: props.booking.hours,
})

function submit() {
  form.put(route('user.bookings.update', props.booking.id))
}
</script>

<template>
  <AppLayout title="Edit Booking">
    <form @submit.prevent="submit" class="bg-white border rounded p-6 max-w-md">
      <div class="grid gap-4">
        <select v-model.number="form.workspace_id" class="border rounded px-3 py-2">
          <option disabled value="">Select workspace</option>
          <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }} ({{ w.price_per_hour }}/h)</option>
        </select>
        <input v-model.number="form.hours" type="number" min="1" class="border rounded px-3 py-2" placeholder="Hours" />
      </div>

      <div class="mt-4 flex items-center gap-3">
        <button class="bg-gray-900 text-white px-4 py-2 rounded" :disabled="form.processing">Save</button>
        <Link :href="route('user.bookings.index')" class="text-gray-600">Cancel</Link>
      </div>

      <div v-if="form.errors" class="mt-3 text-sm text-red-600">
        <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
      </div>
    </form>
  </AppLayout>
</template>
