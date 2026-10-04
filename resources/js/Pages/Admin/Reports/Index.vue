<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
  kpis: { type: Object, default: () => ({}) },
  series: { type: Array, default: () => [] },
  topWorkspaces: { type: Array, default: () => [] },
  topOwners: { type: Array, default: () => [] },
  workspaces: { type: Array, default: () => [] },
  owners: { type: Array, default: () => [] },
})

const form = reactive({
  from: props.filters.from || '',
  to: props.filters.to || '',
  status: props.filters.status || '',
  workspace_id: props.filters.workspace_id || '',
  owner_id: props.filters.owner_id || '',
})

function apply() {
  router.get(route('admin.reports.index'), { ...form }, { preserveState: true, replace: true })
}

function exportCsv() {
  const params = new URLSearchParams()
  Object.entries(form).forEach(([k, v]) => {
    if (v !== '' && v !== null && v !== undefined) params.set(k, v)
  })
  window.location.href = route('admin.reports.export') + '?' + params.toString()
}

const totalInRange = computed(() => (props.series || []).reduce((a, b) => a + Number(b.c || 0), 0))
</script>

<template>
  <AppLayout title="تقارير الإدارة">
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-8">
      <div class="bg-white border rounded-2xl p-4 flex flex-wrap items-end gap-3">
        <div class="grid gap-1">
          <label class="text-xs text-slate-600">من تاريخ</label>
          <input v-model="form.from" type="date" class="border rounded px-3 py-2" />
        </div>
        <div class="grid gap-1">
          <label class="text-xs text-slate-600">إلى تاريخ</label>
          <input v-model="form.to" type="date" class="border rounded px-3 py-2" />
        </div>
        <div class="grid gap-1">
          <label class="text-xs text-slate-600">الحالة</label>
          <select v-model="form.status" class="border rounded px-3 py-2">
            <option value="">الكل</option>
            <option value="pending">معلّق</option>
            <option value="confirmed">مؤكد</option>
            <option value="cancelled">ملغى</option>
            <option value="completed">مكتمل</option>
            <option value="no_show">لم يحضر</option>
          </select>
        </div>
        <div class="grid gap-1">
          <label class="text-xs text-slate-600">المساحة</label>
          <select v-model="form.workspace_id" class="border rounded px-3 py-2">
            <option value="">الكل</option>
            <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        <div class="grid gap-1">
          <label class="text-xs text-slate-600">المالك</label>
          <select v-model="form.owner_id" class="border rounded px-3 py-2">
            <option value="">الكل</option>
            <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        <button @click="apply" class="px-4 py-2 rounded-xl bg-gray-900 text-white">تطبيق</button>
        <button @click="exportCsv" class="px-4 py-2 rounded-xl border">تصدير CSV</button>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-slate-500">Users</div><div class="text-2xl font-bold">{{ kpis.users }}</div></div>
        <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-slate-500">Owners</div><div class="text-2xl font-bold">{{ kpis.owners }}</div></div>
        <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-slate-500">Workspaces</div><div class="text-2xl font-bold">{{ kpis.workspaces }}</div></div>
        <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-slate-500">Bookings</div><div class="text-2xl font-bold">{{ kpis.bookings }}</div></div>
        <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-slate-500">Revenue</div><div class="text-2xl font-bold">${{ Number(kpis.revenue ?? 0).toFixed(2) }}</div></div>
        <div class="bg-white border rounded-2xl p-4"><div class="text-xs text-slate-500">Avg booking</div><div class="text-2xl font-bold">${{ Number(kpis.average_booking_value ?? 0).toFixed(2) }}</div></div>
      </div>

      <div class="bg-white border rounded-2xl p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">الاتجاه اليومي</h2>
          <div class="text-sm text-slate-600">المجموع: {{ totalInRange }}</div>
        </div>
        <div v-if="!series?.length" class="text-slate-500 text-sm">لا توجد بيانات.</div>
        <div v-else class="space-y-2">
          <div v-for="row in series" :key="row.d" class="flex items-center gap-3">
            <div class="w-28 text-xs text-slate-600">{{ row.d }}</div>
            <div class="flex-1 h-2 bg-slate-100 rounded">
              <div class="h-2 bg-indigo-500 rounded" :style="{ width: Math.min(100, Number(row.c) * 10) + '%' }" />
            </div>
            <div class="w-24 text-xs text-right">{{ row.c }} / ${{ Number(row.revenue || 0).toFixed(0) }}</div>
          </div>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white border rounded-2xl p-4">
          <h2 class="font-semibold mb-3">أعلى المساحات</h2>
          <div v-for="w in topWorkspaces" :key="w.id" class="flex justify-between py-2 border-b text-sm">
            <span>{{ w.name }}</span>
            <span>{{ w.count }} · ${{ Number(w.sum).toFixed(2) }}</span>
          </div>
        </div>
        <div class="bg-white border rounded-2xl p-4">
          <h2 class="font-semibold mb-3">أعلى الملاك</h2>
          <div v-for="o in topOwners" :key="o.id" class="flex justify-between py-2 border-b text-sm">
            <span>{{ o.name }}</span>
            <span>{{ o.count }} · ${{ Number(o.sum).toFixed(2) }}</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
