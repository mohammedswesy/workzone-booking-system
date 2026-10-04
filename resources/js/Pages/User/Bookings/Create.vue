<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const workspace = page.props?.workspace ?? null
const workspaces = page.props?.workspaces ?? []

function defaultStart() {
  const d = new Date()
  d.setMinutes(0, 0, 0)
  d.setHours(d.getHours() + 1)
  return toLocalInput(d)
}

function defaultEnd() {
  const d = new Date()
  d.setMinutes(0, 0, 0)
  d.setHours(d.getHours() + 2)
  return toLocalInput(d)
}

function toLocalInput(date) {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const form = useForm({
  workspace_id: workspace?.id ?? null,
  start_at: defaultStart(),
  end_at: defaultEnd(),
})

const selected = computed(() => {
  if (workspace && workspace.id === form.workspace_id) return workspace
  return workspaces.find((w) => w.id === form.workspace_id) ?? workspace
})

const hours = computed(() => {
  if (!form.start_at || !form.end_at) return 0
  const ms = new Date(form.end_at) - new Date(form.start_at)
  return ms > 0 ? ms / 3600000 : 0
})

const total = computed(() => {
  const rate = Number(selected.value?.effective_price_per_hour ?? selected.value?.price_per_hour ?? 0)
  return (rate * hours.value).toFixed(2)
})

function submit() {
  form.post(route('user.bookings.store'), { preserveScroll: true })
}
</script>

<template>
  <AppLayout title="Create Booking">
    <div class="max-w-3xl mx-auto p-4 space-y-6">
      <h1 class="text-xl font-semibold">Create Booking</h1>

      <form @submit.prevent="submit" class="border rounded-xl p-4 space-y-3 bg-white">
        <label class="grid gap-1">
          <span class="text-sm text-gray-600">Workspace</span>
          <select v-model.number="form.workspace_id" class="border rounded px-3 py-2" required>
            <option :value="null" disabled>Select a workspace</option>
            <option v-for="w in workspaces" :key="w.id" :value="w.id">
              {{ w.name }} — ${{ Number(w.effective_price_per_hour ?? w.price_per_hour).toFixed(2) }}/h
            </option>
          </select>
        </label>

        <div class="grid md:grid-cols-2 gap-3">
          <label class="grid gap-1">
            <span class="text-sm text-gray-600">Start</span>
            <input v-model="form.start_at" type="datetime-local" class="border rounded px-3 py-2" required />
          </label>
          <label class="grid gap-1">
            <span class="text-sm text-gray-600">End</span>
            <input v-model="form.end_at" type="datetime-local" class="border rounded px-3 py-2" required />
          </label>
        </div>

        <p v-if="selected" class="text-sm text-slate-600">
          Open {{ selected.opening_time }} – {{ selected.closing_time }}
          <span v-if="selected.active_discount_percent"> · Offer {{ selected.active_discount_percent }}% off</span>
        </p>

        <div class="text-slate-800">
          Duration: <span class="font-medium">{{ hours.toFixed(2) }}h</span>
          · Estimated total:
          <span class="font-semibold">$ {{ total }}</span>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button
            type="submit"
            class="bg-indigo-600 text-white px-4 py-2 rounded disabled:opacity-50"
            :disabled="form.processing"
          >
            Confirm Booking
          </button>
          <Link :href="route('spaces.index')" class="px-3 py-2 border rounded hover:bg-gray-50">Back</Link>
        </div>

        <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600">
          <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
