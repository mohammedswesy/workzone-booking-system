<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

// نقرأ workspace من الـ props (الكنترولر بيرسّلها)، ولو مش موجودة ناخد id من الـ query
const workspace = page.props?.workspace ?? null
const queryId =
  page.props?.ziggy?.query?.workspace_id ??
  page.props?.query?.workspace_id ??
  null

const form = useForm({
  workspace_id: workspace?.id ?? (queryId ? Number(queryId) : null),
  hours: 1,
})

const total = computed(() => {
  const price = Number(workspace?.price_per_hour ?? 0)
  const h = Number(form.hours ?? 0)
  return (price * h).toFixed(2)
})

function submit () {
  form.post(route('user.bookings.store'), { preserveScroll: true })
}
</script>

<template>
  <AppLayout title="Create Booking">
    <div class="max-w-3xl mx-auto p-4 space-y-6">
      <h1 class="text-xl font-semibold">Create Booking</h1>

      <!-- لو ما في مساحة محددة -->
      <div v-if="!workspace" class="border rounded-lg p-4">
        <p class="text-slate-700 mb-3">
          Please pick a workspace first from the spaces page.
        </p>

        <form @submit.prevent="submit" class="grid gap-3 max-w-md">
          <label class="grid gap-1">
            <span class="text-sm text-gray-600">Workspace ID</span>
            <input
              v-model.number="form.workspace_id"
              type="number"
              min="1"
              placeholder="e.g. 1"
              class="border rounded px-3 py-2"
            />
          </label>

          <label class="grid gap-1">
            <span class="text-sm text-gray-600">Hours</span>
            <input
              v-model.number="form.hours"
              type="number"
              min="1"
              class="border rounded px-3 py-2"
              placeholder="Hours"
            />
          </label>

          <div class="flex items-center gap-3">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded" :disabled="form.processing">
              Book
            </button>
            <Link :href="route('spaces.index')" class="px-3 py-2 border rounded hover:bg-gray-50">
              Back to spaces
            </Link>
          </div>

          <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600">
            <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
          </div>
        </form>
      </div>

      <!-- لو في مساحة محددة -->
      <div v-else class="grid md:grid-cols-2 gap-4">
        <div class="border rounded-xl p-4">
          <img
            :src="workspace.image_url || 'https://placehold.co/600x400?text=Workspace'"
            class="w-full h-40 object-cover rounded mb-3"
            :alt="workspace.name"
          />
          <div class="font-semibold text-lg">{{ workspace.name }}</div>
          <div class="text-slate-600">{{ workspace.location }}</div>
          <div class="mt-1 text-slate-700">Capacity: {{ workspace.capacity }}</div>
          <div class="text-slate-700">Price/hour: $ {{ Number(workspace.price_per_hour).toFixed(2) }}</div>
        </div>

        <form @submit.prevent="submit" class="border rounded-xl p-4 space-y-3">
          <input type="hidden" v-model="form.workspace_id" />

          <label class="block text-sm font-medium">Hours</label>
          <input
            v-model.number="form.hours"
            type="number"
            min="1"
            class="w-full border rounded px-3 py-2"
            placeholder="Hours"
          />

          <div class="text-sm text-red-600" v-if="form.errors.hours">{{ form.errors.hours }}</div>

          <div class="pt-2 text-slate-800">
            Total: <span class="font-semibold">$ {{ total }}</span>
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button
              type="submit"
              class="bg-indigo-600 text-white px-4 py-2 rounded disabled:opacity-50"
              :disabled="form.processing"
            >
              Confirm Booking
            </button>
            <Link :href="route('spaces.index')" class="px-3 py-2 border rounded hover:bg-gray-50">
              Back
            </Link>
          </div>

          <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600">
            <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
