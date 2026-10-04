<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  booking: Object,
  workspaces: Array,
})

function toLocalInput(value) {
  if (!value) return ''
  const d = new Date(value)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const form = useForm({
  workspace_id: props.booking.workspace_id,
  start_at: toLocalInput(props.booking.start_at),
  end_at: toLocalInput(props.booking.end_at),
})

function submit() {
  form.put(route('user.bookings.update', props.booking.id))
}
</script>

<template>
  <AppLayout title="Edit Booking">
    <form @submit.prevent="submit" class="bg-white border rounded p-6 max-w-md space-y-3">
      <select v-model.number="form.workspace_id" class="border rounded px-3 py-2 w-full">
        <option disabled :value="null">Select workspace</option>
        <option v-for="w in workspaces" :key="w.id" :value="w.id">
          {{ w.name }} ({{ w.effective_price_per_hour ?? w.price_per_hour }}/h)
        </option>
      </select>

      <input v-model="form.start_at" type="datetime-local" class="border rounded px-3 py-2 w-full" />
      <input v-model="form.end_at" type="datetime-local" class="border rounded px-3 py-2 w-full" />

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
