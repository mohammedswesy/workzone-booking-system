<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
  workspaces: { type: Array, default: () => [] }, // [{id,name}]
})

const form = useForm({
  workspace_id: props.workspaces[0]?.id ?? null,
  title: '',
  discount_percent: 10,
  starts_at: new Date().toISOString().slice(0,10),
  ends_at: new Date().toISOString().slice(0,10),
  is_active: true,
})

function submit() {
  form.post(route('owner.offers.store'))
}
</script>

<template>
  <AppLayout title="إضافة عرض">
    <form @submit.prevent="submit" class="max-w-xl bg-white border rounded p-6 space-y-4">
      <div class="grid gap-3">
        <label class="grid gap-1">
          <span class="text-sm text-slate-600">المساحة</span>
          <select v-model.number="form.workspace_id" class="border rounded px-3 py-2">
            <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="text-sm text-slate-600">عنوان العرض</span>
          <input v-model="form.title" class="border rounded px-3 py-2" placeholder="مثال: خصم افتتاح" />
        </label>

        <div class="grid grid-cols-2 gap-3">
          <label class="grid gap-1">
            <span class="text-sm text-slate-600">نسبة الخصم %</span>
            <input v-model.number="form.discount_percent" type="number" min="1" max="100" class="border rounded px-3 py-2" />
          </label>
          <label class="grid gap-1">
            <span class="text-sm text-slate-600">فعّال؟</span>
            <input v-model="form.is_active" type="checkbox" class="w-5 h-5" />
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="grid gap-1">
            <span class="text-sm text-slate-600">يبدأ في</span>
            <input v-model="form.starts_at" type="date" class="border rounded px-3 py-2" />
          </label>
          <label class="grid gap-1">
            <span class="text-sm text-slate-600">ينتهي في</span>
            <input v-model="form.ends_at" type="date" class="border rounded px-3 py-2" />
          </label>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button class="bg-indigo-600 text-white px-4 py-2 rounded" :disabled="form.processing">
          حفظ
        </button>
        <Link :href="route('owner.offers.index')" class="text-slate-600">رجوع</Link>
      </div>

      <div v-if="Object.keys(form.errors).length" class="text-red-600 text-sm">
        <div v-for="(m, k) in form.errors" :key="k">{{ m }}</div>
      </div>
    </form>
  </AppLayout>
</template>
