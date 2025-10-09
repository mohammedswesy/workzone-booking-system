<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  location: '',
  capacity: 1,
  price_per_hour: 0,
  image_url: '',
  image: null, // رفع صورة (اختياري)
})

function submit() {
  form.post(route('owner.workspaces.store'), {
    forceFormData: true,
    onSuccess: () => form.reset('name','location','capacity','price_per_hour','image_url','image')
  })
}
</script>

<template>
  <AppLayout title="إضافة مساحة">
    <div class="max-w-xl mx-auto px-4 py-6 space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">إضافة مساحة</h1>
        <Link :href="route('owner.workspaces.index')" class="underline">رجوع</Link>
      </div>

      <div class="grid gap-4 bg-white shadow rounded-lg p-6">
        <div>
          <input v-model="form.name" placeholder="اسم المساحة"
                 class="border rounded px-3 py-2 w-full" />
          <div v-if="form.errors.name" class="text-red-600 text-sm">{{ form.errors.name }}</div>
        </div>

        <div>
          <input v-model="form.location" placeholder="الموقع"
                 class="border rounded px-3 py-2 w-full" />
          <div v-if="form.errors.location" class="text-red-600 text-sm">{{ form.errors.location }}</div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <input type="number" min="1" v-model.number="form.capacity"
                   placeholder="السعة" class="border rounded px-3 py-2 w-full" />
            <div v-if="form.errors.capacity" class="text-red-600 text-sm">{{ form.errors.capacity }}</div>
          </div>
          <div>
            <input type="number" min="0" step="0.5" v-model.number="form.price_per_hour"
                   placeholder="السعر/ساعة" class="border rounded px-3 py-2 w-full" />
            <div v-if="form.errors.price_per_hour" class="text-red-600 text-sm">{{ form.errors.price_per_hour }}</div>
          </div>
        </div>

        <div>
          <input v-model="form.image_url" placeholder="رابط الصورة (اختياري)"
                 class="border rounded px-3 py-2 w-full" />
          <div v-if="form.errors.image_url" class="text-red-600 text-sm">{{ form.errors.image_url }}</div>
        </div>

        <div>
          <input type="file" @change="e => form.image = e.target.files[0]" />
          <div v-if="form.errors.image" class="text-red-600 text-sm">{{ form.errors.image }}</div>
        </div>

        <button @click="submit" :disabled="form.processing"
                class="w-full bg-blue-600 text-white rounded px-4 py-2">
          {{ form.processing ? 'جاري الحفظ...' : 'حفظ' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>
