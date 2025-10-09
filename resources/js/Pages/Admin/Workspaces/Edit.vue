<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
const props = defineProps({ workspace: Object })

const form = useForm({
  name: props.workspace.name,
  location: props.workspace.location,
  capacity: props.workspace.capacity,
  price_per_hour: props.workspace.price_per_hour,
  owner_id: props.workspace.owner_id ?? '',
  image_url: props.workspace.image_url ?? '',
})
function submit(){ form.put(route('admin.workspaces.update', props.workspace.id)) }
</script>

<template>
  <AppLayout title="تعديل مساحة">
    <div class="max-w-xl mx-auto px-4 py-6">
      <h1 class="text-xl font-semibold mb-4">تعديل مساحة</h1>
      <form @submit.prevent="submit" class="bg-white border rounded p-4 space-y-3">
        <input v-model="form.name" class="border rounded px-3 py-2 w-full" placeholder="الاسم" />
        <input v-model="form.location" class="border rounded px-3 py-2 w-full" placeholder="الموقع" />
        <input v-model.number="form.capacity" type="number" min="1" class="border rounded px-3 py-2 w-full" placeholder="السعة" />
        <input v-model.number="form.price_per_hour" type="number" min="0" step="0.5" class="border rounded px-3 py-2 w-full" placeholder="السعر/ساعة" />
        <input v-model="form.owner_id" class="border rounded px-3 py-2 w-full" placeholder="(اختياري) رقم المالك" />
        <input v-model="form.image_url" class="border rounded px-3 py-2 w-full" placeholder="رابط الصورة" />

        <div class="flex items-center gap-3">
          <button class="bg-gray-900 text-white px-4 py-2 rounded" :disabled="form.processing">تحديث</button>
          <Link :href="route('admin.workspaces.index')" class="text-slate-600">رجوع</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
