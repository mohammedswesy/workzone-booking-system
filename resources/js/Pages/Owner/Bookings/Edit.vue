<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
  booking: { type: Object, required: true },
  statuses: { type: Array, default: () => ['pending','paid','cancelled'] }
})

const form = useForm({
  status: props.booking.status ?? 'pending'
})

function submit() {
  form.put(route('owner.bookings.update', props.booking.id))
}
</script>

<template>
  <AppLayout title="تعديل الحجز">
    <div class="max-w-xl mx-auto px-4 py-6 space-y-4">
      <h1 class="text-xl font-semibold">تعديل حجز #{{ booking.id }}</h1>

      <form @submit.prevent="submit" class="border rounded-xl p-4 space-y-3 bg-white">
        <div>
          <label class="block text-sm text-slate-600 mb-1">الحالة</label>
          <select v-model="form.status" class="border rounded px-3 py-2 w-full">
            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
          </select>
          <div v-if="form.errors.status" class="text-red-600 text-sm mt-1">{{ form.errors.status }}</div>
        </div>

        <div class="flex items-center gap-3">
          <button class="bg-gray-900 text-white px-4 py-2 rounded" :disabled="form.processing">
            حفظ
          </button>
          <Link :href="route('owner.bookings.index')" class="text-slate-600">رجوع</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
