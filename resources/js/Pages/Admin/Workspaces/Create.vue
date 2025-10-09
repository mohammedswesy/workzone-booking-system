<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  location: '',
  capacity: 1,
  price_per_hour: 0,
  owner_id: '',   // اختياري: تعيين المالك
  image_url: '',
})

function submit() {
  form.post(route('admin.workspaces.store'))
}
</script>

<template>
  <AppLayout title="إضافة مساحة">
    <div class="max-w-xl mx-auto px-4 py-6">
      <h1 class="text-xl font-semibold mb-4">إضافة مساحة</h1>
      <form @submit.prevent="submit" class="bg-white border rounded p-4 space-y-3">
        <input v-model="form.name" class="border rounded px-3 py-2 w-full" placeholder="الاسم" />
        <input v-model="form.location" class="border rounded px-3 py-2 w-full" placeholder="الموقع" />
        <input v-model.number="form.capacity" type="number" min="1" class="border rounded px-3 py-2 w-full" placeholder="السعة" />
        <input v-model.number="form.price_per_hour" type="number" min="0" step="0.5" class="border rounded px-3 py-2 w-full" placeholder="السعر/ساعة" />
        <input v-model="form.owner_id" class="border rounded px-3 py-2 w-full" placeholder="(اختياري) رقم المالك" />
        <input v-model="form.image_url" class="border rounded px-3 py-2 w-full" placeholder="رابط الصورة" />

        <div class="flex items-center gap-3">
          <button class="bg-gray-900 text-white px-4 py-2 rounded" :disabled="form.processing">حفظ</button>
          <Link :href="route('admin.workspaces.index')" class="text-slate-600">رجوع</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
