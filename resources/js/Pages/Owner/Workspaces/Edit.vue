<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
  // يأتي من الكنترولر: only('id','name','location','capacity','price_per_hour','image_url')
  workspace: { type: Object, required: true }
})

const form = useForm({
  name: props.workspace.name ?? '',
  location: props.workspace.location ?? '',
  capacity: props.workspace.capacity ?? 1,
  price_per_hour: props.workspace.price_per_hour ?? 0,
  image_url: props.workspace.image_url ?? '', // إن حابب تتحكم بالرابط
  image: null, // اختيار صورة بديلة يرفعها ويستبدلها في السيرفر
})

function submit() {
  // أبسط: put مباشرة، مع forceFormData لأن فيه ملف محتمل
  form.put(route('owner.workspaces.update', props.workspace.id), {
    forceFormData: true,
    preserveScroll: true,
  })
}
</script>

<template>
  <AppLayout title="تعديل المساحة">
    <div class="max-w-xl mx-auto p-6">
      <h1 class="text-xl font-semibold mb-4">تعديل: {{ props.workspace.name }}</h1>

      <form @submit.prevent="submit" class="bg-white border rounded p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">اسم المساحة</label>
          <input v-model="form.name" class="border rounded px-3 py-2 w-full" />
          <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">الموقع</label>
          <input v-model="form.location" class="border rounded px-3 py-2 w-full" />
          <div v-if="form.errors.location" class="text-red-600 text-sm mt-1">{{ form.errors.location }}</div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">السعة</label>
            <input v-model.number="form.capacity" type="number" min="1" class="border rounded px-3 py-2 w-full" />
            <div v-if="form.errors.capacity" class="text-red-600 text-sm mt-1">{{ form.errors.capacity }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">السعر/ساعة</label>
            <input v-model.number="form.price_per_hour" type="number" min="0" step="0.01" class="border rounded px-3 py-2 w-full" />
            <div v-if="form.errors.price_per_hour" class="text-red-600 text-sm mt-1">{{ form.errors.price_per_hour }}</div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">رابط صورة (اختياري)</label>
          <input v-model="form.image_url" class="border rounded px-3 py-2 w-full" />
          <div v-if="form.errors.image_url" class="text-red-600 text-sm mt-1">{{ form.errors.image_url }}</div>
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium">رفع صورة بديلة (اختياري)</label>
          <input type="file" accept="image/*" @change="e => form.image = e.target.files[0]" />
          <div v-if="form.errors.image" class="text-red-600 text-sm mt-1">{{ form.errors.image }}</div>

          <!-- معاينة الحالية -->
          <div v-if="props.workspace.image_url" class="text-sm text-gray-600">
            الصورة الحالية:
            <img :src="props.workspace.image_url" alt="" class="mt-2 h-24 w-full object-cover rounded border" />
          </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button class="bg-gray-900 text-white px-4 py-2 rounded" :disabled="form.processing">
            {{ form.processing ? 'جارِ الحفظ…' : 'حفظ' }}
          </button>
          <Link :href="route('owner.workspaces.index')" class="text-gray-600 hover:underline">رجوع</Link>
        </div>

        <!-- تجميع الأخطاء العامة إن وجِدت -->
        <div v-if="Object.keys(form.errors).length" class="mt-3 text-sm text-red-600 space-y-1">
          <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
